<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class HoldingSsoTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected string $secret = 'holding-sso-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['services.holding_sso.secret' => $this->secret]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();

        parent::tearDown();
    }

    public function test_valid_token_creates_user_binds_tenant_and_consumes_cache(): void
    {
        $token = $this->putPayload($this->payload());

        $this->get('/auth/holding?token='.$token)->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'owner@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame((int) $this->testTenant->row_id, (int) $user->tenant_id);
        $this->assertFalse((bool) $user->is_superadmin);
        $this->assertAuthenticatedAs($user);
        $this->assertNull(Cache::get('sso:'.hash('sha256', $token)));
    }

    public function test_token_cannot_be_used_twice(): void
    {
        $token = $this->putPayload($this->payload());

        $this->get('/auth/holding?token='.$token)->assertRedirect('/dashboard');
        Auth::forgetGuards();
        $this->flushSession();

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = $this->putPayload($this->payload(['exp' => time() - 1]));

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0, 'platform');
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = $this->payload();
        $payload['signature'] = str_repeat('0', 64);
        $token = $this->putPayload($payload);

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0, 'platform');
    }

    public function test_unsigned_payload_is_rejected_when_secret_is_configured(): void
    {
        $token = $this->putPayload($this->payload(), signed: false);

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0, 'platform');
    }

    public function test_unknown_sub_tenant_code_creates_no_user(): void
    {
        $token = $this->putPayload($this->payload(['sub_tenant_code' => 'missing']));

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Tenant tujuan tidak ditemukan.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0, 'platform');
    }

    public function test_holding_superadmin_does_not_become_local_superadmin(): void
    {
        $token = $this->putPayload($this->payload(['role' => 'tenant_owner']));

        $this->get('/auth/holding?token='.$token)->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'owner@example.test')->first();

        $this->assertNotNull($user);
        $this->assertFalse((bool) $user->is_superadmin);

        $token = $this->putPayload($this->payload(['role' => 'superadmin']));

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($user);
        $this->assertFalse((bool) $user->fresh()?->is_superadmin);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tenant_application_id' => 12,
            'user_id' => 34,
            'email' => 'owner@example.test',
            'name' => 'Owner Tenant',
            'role' => 'tenant_owner',
            'tenant_name' => 'BUMDesma Contoh',
            'sub_tenant_code' => $this->testTenant->code,
            'exp' => time() + 60,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function putPayload(array $payload, bool $signed = true): string
    {
        if ($signed) {
            $payload['signature'] = hash_hmac(
                'sha256',
                json_encode($payload, JSON_THROW_ON_ERROR),
                $this->secret,
            );
        }

        $token = bin2hex(random_bytes(32));
        Cache::put('sso:'.hash('sha256', $token), $payload, 60);

        return $token;
    }
}
