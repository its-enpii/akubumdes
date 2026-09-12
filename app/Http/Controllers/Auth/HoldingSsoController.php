<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Services\PermissionChecker;
use App\Http\Controllers\Controller;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class HoldingSsoController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ShardConnectionManager $connections,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        try {
            $payload = $this->consumePayload($request);
        } catch (Throwable $exception) {
            // An unverifiable token (unknown, replayed, expired, bad signature)
            // ends the browser session: no trusted identity was ever proven,
            // so nothing from the handshake may survive it.
            $this->releaseTenantContext();
            $this->invalidateSession($request);

            return redirect()->route('login')->with('error', 'Sesi SSO tidak valid atau sudah kedaluwarsa.');
        }

        try {
            $user = $this->loginOrCreateUser($payload);
        } catch (Throwable $exception) {
            // The token itself verified, so only the tenant/role mapping was
            // refused; an already authenticated session keeps running.
            $this->releaseTenantContext();

            return redirect()->route('login')->with('error', $this->rejectReason($exception));
        }

        $request->session()->regenerate();
        Auth::login($user);

        return redirect()->to(Route::has('dashboard') ? route('dashboard') : '/login');
    }

    private function rejectReason(Throwable $exception): string
    {
        return in_array($exception->getMessage(), [
            'Tenant tujuan tidak ditemukan.',
            'Tenant tujuan tidak aktif.',
            'Akun SSO sudah terikat pada tenant lain.',
        ], true) ? $exception->getMessage() : 'Sesi SSO tidak valid atau sudah kedaluwarsa.';
    }

    private function invalidateSession(Request $request): void
    {
        if (Auth::check()) {
            Auth::logout();
        }

        // invalidate() already flushes the data and rotates the CSRF token.
        $request->session()->invalidate();
    }

    /**
     * @return array<string, mixed>
     */
    private function consumePayload(Request $request): array
    {
        $token = (string) $request->query('token', '');

        if ($token === '') {
            throw new RuntimeException('Token SSO wajib diisi.');
        }

        $cacheKey = 'sso:'.hash('sha256', $token);
        $payload = Cache::pull($cacheKey);

        if (! is_array($payload)) {
            throw new RuntimeException('Token SSO tidak valid.');
        }

        $this->verifyPayload($payload);
        $this->verifySignature($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifyPayload(array $payload): void
    {
        foreach (['email', 'name', 'role'] as $key) {
            if (! is_string($payload[$key] ?? null) || trim((string) $payload[$key]) === '') {
                throw new RuntimeException('Payload SSO tidak valid.');
            }
        }

        if (filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Email SSO tidak valid.');
        }

        if ((int) ($payload['exp'] ?? 0) <= time()) {
            throw new RuntimeException('Token SSO sudah kedaluwarsa.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifySignature(array $payload): void
    {
        $signature = $payload['signature'] ?? null;
        $secret = (string) config('services.holding_sso.secret');

        if ($secret !== '' && $signature === null) {
            throw new RuntimeException('Signature SSO wajib disertakan.');
        }

        if ($secret === '' && $signature !== null) {
            throw new RuntimeException('Signature SSO ditolak.');
        }

        if ($secret === '') {
            return;
        }

        $unsigned = $payload;
        unset($unsigned['signature']);

        $expected = hash_hmac('sha256', json_encode($unsigned, JSON_THROW_ON_ERROR), $secret);

        if (! is_string($signature) || ! hash_equals($expected, $signature)) {
            throw new RuntimeException('Signature SSO tidak valid.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function loginOrCreateUser(array $payload): User
    {
        $email = strtolower((string) $payload['email']);
        $existingUser = User::query()->where('email', $email)->first();

        $tenant = $this->resolveTenant($payload, $existingUser);
        $this->guardTenantOwnership($existingUser, $tenant);
        $this->activateTenantContext($tenant);
        $roleCode = $this->resolveRoleCode($payload);

        $user = $existingUser ?? new User;
        $isNewUser = ! $user->exists;

        $user->fill([
            'email' => $email,
            'name' => (string) $payload['name'],
            'tenant_id' => $tenant->row_id,
        ]);

        if ($isNewUser) {
            $user->public_id = (string) Str::ulid();
            $user->username = $this->uniqueUsername($email);
            $user->password = Hash::make(bin2hex(random_bytes(20)));
            $user->phone = $this->phonePlaceholder($user->public_id);
        }

        $user->save();

        TenantMembership::query()->updateOrCreate(
            ['tenant_id' => $tenant->row_id, 'user_id' => $user->row_id],
            ['status' => 'active', 'joined_at' => now()],
        );

        $this->assignRole($user, $roleCode);

        return $user;
    }

    /**
     * Holding roles are deliberately mapped to the tenant administrator role;
     * holding superadmin may never become a local superadmin.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveRoleCode(array $payload): string
    {
        return match ((string) $payload['role']) {
            'tenant_owner', 'tenant_staff' => 'admin',
            'superadmin' => throw new RuntimeException('Role SSO tidak diizinkan.'),
            default => throw new RuntimeException('Role SSO tidak valid.'),
        };
    }

    private function guardTenantOwnership(?User $user, Tenant $tenant): void
    {
        if ($user?->tenant_id !== null && (int) $user->tenant_id !== (int) $tenant->row_id) {
            throw new RuntimeException('Akun SSO sudah terikat pada tenant lain.');
        }
    }

    private function uniqueUsername(string $email): string
    {
        $candidate = substr($this->slugify((string) Str::before($email, '@')), 0, 80);
        $username = $candidate === '' ? 'pengguna' : $candidate;
        $sequence = 1;

        while (User::query()->where('username', $username)->exists()) {
            $username = substr($candidate, 0, 70).'.'.++$sequence;
        }

        return $username;
    }

    /**
     * Holding SSO never carries a WhatsApp number, and users.phone is a
     * NOT NULL unique column, so new accounts get the same deterministic
     * placeholder the phone migration backfills: non-numeric values are
     * rejected by the OTP services until the user sets a real number.
     */
    private function phonePlaceholder(string $publicId): string
    {
        return 'pending-wa-'.substr(md5($publicId), 0, 8);
    }

    private function slugify(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9._-]+/', '.', strtolower($value)) ?? '';

        return trim($slug, '.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTenant(array $payload, ?User $existingUser): Tenant
    {
        $subTenantCode = $payload['sub_tenant_code'] ?? null;

        if ($subTenantCode !== null) {
            try {
                return app(TenantResolver::class)->resolveByCode((string) $subTenantCode);
            } catch (Throwable) {
                throw new RuntimeException('Tenant tujuan tidak ditemukan.');
            }
        }

        if ($existingUser?->tenant_id !== null) {
            return app(TenantResolver::class)->resolveForUser($existingUser);
        }

        $defaultTenant = Tenant::query()->where('code', 'local')->first();

        if ($defaultTenant === null) {
            throw new RuntimeException('Tenant tujuan tidak ditemukan.');
        }

        return $defaultTenant;
    }

    private function activateTenantContext(Tenant $tenant): void
    {
        $tenant->loadMissing('placement.shard');
        $placement = $tenant->placement;
        $shard = $placement?->shard;

        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant tujuan tidak aktif.');
        }

        $this->connections->connect($shard);
        $this->context->initialize($tenant, $placement, $shard);
    }

    private function assignRole(User $user, string $roleCode): void
    {
        app(PermissionChecker::class)->assignRole($user, $roleCode);
    }

    private function releaseTenantContext(): void
    {
        $this->context->clear();
        $this->connections->disconnect();
    }
}
