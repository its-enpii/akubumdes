<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Migration\Accounting\LegacyAccountingDiscovery;
use App\Domain\Migration\Support\LegacyConnection;
use App\Http\Controllers\Controller;
use App\Jobs\RunTenantCutoverJob;
use App\Models\Platform\CutoverRun;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Services\Admin\TenantCutoverRunnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MigrationController extends Controller
{
    /**
     * Cache TTL for legacy suffix discovery (seconds). Discovery runs MIN/MAX
     * queries against the remote legacy MySQL across all suffixes — too slow
     * to call on every page render, so we cache the result for 5 minutes.
     */
    private const DISCOVERY_CACHE_TTL = 300;

    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->select(['row_id', 'code', 'name', 'status', 'coa_variant'])
            ->orderBy('name')
            ->get();

        $discoveredSuffixes = [];

        $runs = CutoverRun::query()
            ->with(['tenant:row_id,code,name'])
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn ($run) => [
                'id' => $run->id,
                'tenant_id' => $run->tenant_id,
                'tenant_code' => $run->tenant_code,
                'tenant_name' => $run->tenant?->name ?? $run->tenant_code,
                'suffix' => $run->suffix,
                'is_dry_run' => $run->is_dry_run,
                'options' => $run->options,
                'status' => $run->status,
                'steps' => $run->steps,
                'error_message' => $run->error_message,
                'output_log' => $run->output_log,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'created_at' => $run->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Migration/Index', [
            'tenants' => $tenants,
            'runs' => $runs,
            'legacy_config' => [
                'host' => (string) config('database.connections.legacy.host', '127.0.0.1'),
                'port' => (int) config('database.connections.legacy.port', 3306),
                'database' => (string) config('database.connections.legacy.database', 'simak'),
            ],
            'discovered_suffixes' => $discoveredSuffixes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'legacy_id' => ['nullable', 'integer'],
            'tenant_id' => ['nullable', 'integer', 'exists:platform.tenants,row_id'],
            'suffix' => ['nullable', 'numeric'],
            'auto_provision' => ['boolean'],
            'is_dry_run' => ['boolean'],
            'chunk' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'from_year' => ['nullable', 'integer', 'min:2000'],
            'to_year' => ['nullable', 'integer', 'min:2000'],
            'skip_fiscal' => ['boolean'],
            'skip_coa' => ['boolean'],
            'skip_accounting' => ['boolean'],
            'skip_sequences' => ['boolean'],
            'continue_on_error' => ['boolean'],
            'no_fail_fast' => ['boolean'],
            'run_immediately' => ['boolean'],
        ]);

        $isSimpleMode = isset($validated['legacy_id']);
        $legacy = null;
        $autoProvisioned = false;

        if ($isSimpleMode) {
            $legacyConnection = app(LegacyConnection::class);

            try {
                $legacy = $legacyConnection->selectOne(
                    'SELECT id, nama_usaha, jenis_akun, kd_desa FROM usaha WHERE id = ? LIMIT 1',
                    [(int) $validated['legacy_id']],
                );
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'legacy_id' => 'Tidak dapat membaca database legacy: '.$e->getMessage(),
                ]);
            }

            if ($legacy === null) {
                throw ValidationException::withMessages([
                    'legacy_id' => 'Usaha legacy tidak ditemukan.',
                ]);
            }

            $legacyCode = trim((string) ($legacy->kd_desa ?? ''));
            $jenisAkun = (int) ($legacy->jenis_akun ?? 5);
            $variant = match ($jenisAkun) {
                7 => 'trading',
                8 => 'cooperative',
                default => 'standard',
            };

            $tenant = null;
            if ($legacyCode !== '') {
                $tenant = Tenant::query()->where('district_code', $legacyCode)->first();
            }

            if ($tenant === null && (bool) ($validated['auto_provision'] ?? false)) {
                $tenant = $this->autoProvisionTenant($legacyCode, (string) $legacy->nama_usaha, $jenisAkun);
                $autoProvisioned = true;
            }

            if ($tenant === null && isset($validated['tenant_id'])) {
                $tenant = Tenant::query()->where('row_id', (int) $validated['tenant_id'])->first();
            }

            if ($tenant === null) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'Tenant Next belum tersedia untuk kd_desa '.$legacyCode.'. Pilih tenant manual atau aktifkan pembuatan tenant otomatis.',
                ]);
            }

            $suffix = (string) $legacy->id;
            $runOptions = [
                'legacy_id' => (int) $legacy->id,
                'legacy_name' => (string) $legacy->nama_usaha,
                'legacy_code' => $legacyCode,
                'jenis_akun' => $jenisAkun,
                'coa_variant' => $variant,
                'auto_provisioned' => $autoProvisioned,
            ];
        } else {
            if (! isset($validated['tenant_id'], $validated['suffix'])) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'Mode expert memerlukan tenant target dan suffix lokasi.',
                ]);
            }

            $tenant = Tenant::query()->where('row_id', (int) $validated['tenant_id'])->firstOrFail();
            $suffix = (string) $validated['suffix'];
            $runOptions = [];
        }

        $run = CutoverRun::query()->create([
            'tenant_id' => $tenant->row_id,
            'tenant_code' => $tenant->code,
            'suffix' => $suffix,
            'is_dry_run' => (bool) ($validated['is_dry_run'] ?? false),
            'options' => $runOptions + [
                'chunk' => (int) ($validated['chunk'] ?? 500),
                'from_year' => (int) ($validated['from_year'] ?? 2018),
                'to_year' => (int) ($validated['to_year'] ?? (int) date('Y')),
                'skip_fiscal' => (bool) ($validated['skip_fiscal'] ?? false),
                'skip_coa' => (bool) ($validated['skip_coa'] ?? false),
                'skip_accounting' => (bool) ($validated['skip_accounting'] ?? false),
                'skip_sequences' => (bool) ($validated['skip_sequences'] ?? false),
                'continue_on_error' => (bool) ($validated['continue_on_error'] ?? false),
                'no_fail_fast' => (bool) ($validated['no_fail_fast'] ?? false),
                'created_by_user_id' => (int) $request->user()?->row_id,
            ],
            'status' => 'pending',
        ]);

        if (! empty($validated['run_immediately'])) {
            app(TenantCutoverRunnerService::class)->execute($run);
        } else {
            RunTenantCutoverJob::dispatch($run);
        }

        return redirect()->back()->with('success', sprintf(
            'Proses migrasi data untuk tenant "%s" (suffix: %s) berhasil didaftarkan.',
            $tenant->name,
            $suffix,
        ));
    }

    /**
     * Lightweight legacy usaha list with Next tenant enrichment.
     */
    public function legacyTenants(Request $request): JsonResponse
    {
        $cacheKey = 'admin.migration.legacy_tenants.v2';

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        try {
            $legacyTenants = Cache::remember(
                $cacheKey,
                self::DISCOVERY_CACHE_TTL,
                fn () => $this->resolveLegacyTenants(),
            );
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'legacy_tenants' => [],
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'count' => count($legacyTenants),
            'cached_until' => now()->addSeconds(self::DISCOVERY_CACHE_TTL)->toIso8601String(),
            'legacy_tenants' => $legacyTenants,
        ]);
    }

    /**
     * @return list<array{
     *   legacy_id: int,
     *   legacy_name: string,
     *   legacy_code: string,
     *   jenis_akun: int,
     *   jenis_akun_label: string,
     *   coa_variant: string,
     *   next_tenant: array{row_id: int, code: string, name: string, coa_variant: string}|null
     * }>
     */
    public function resolveLegacyTenants(): array
    {
        $rows = app(LegacyConnection::class)->select(
            'SELECT id, nama_usaha, jenis_akun, kd_desa FROM usaha ORDER BY nama_usaha ASC',
        );

        $codes = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->kd_desa ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        $nextTenants = Tenant::query()
            ->where(function ($q) use ($codes): void {
                if ($codes !== []) {
                    $q->whereIn('district_code', array_values(array_unique($codes)))
                        ->orWhereIn('code', array_values(array_unique($codes)));
                }
            })
            ->get(['row_id', 'code', 'name', 'district_code', 'coa_variant']);

        $nextByDistrict = [];
        $nextByCode = [];
        foreach ($nextTenants as $t) {
            if ($t->district_code) {
                $nextByDistrict[$t->district_code] = $t;
            }
            $nextByCode[$t->code] = $t;
        }

        return array_map(static function (object $row) use ($nextByDistrict, $nextByCode): array {
            $code = trim((string) ($row->kd_desa ?? ''));
            $jenisAkun = (int) ($row->jenis_akun ?? 5);
            $variant = match ($jenisAkun) {
                7 => 'trading',
                8 => 'cooperative',
                default => 'standard',
            };
            $variantLabel = match ($jenisAkun) {
                7 => 'Trading',
                8 => 'Cooperative',
                default => 'Standard',
            };

            $matchedTenant = null;
            if ($code !== '') {
                $matchedTenant = $nextByDistrict[$code] ?? $nextByCode[$code] ?? null;
            }

            return [
                'legacy_id' => (int) $row->id,
                'legacy_name' => (string) ($row->nama_usaha ?? 'Usaha #'.$row->id),
                'legacy_code' => $code,
                'jenis_akun' => $jenisAkun,
                'jenis_akun_label' => $variantLabel,
                'coa_variant' => $variant,
                'next_tenant' => $matchedTenant !== null
                    ? [
                        'row_id' => (int) $matchedTenant->row_id,
                        'code' => (string) $matchedTenant->code,
                        'name' => (string) $matchedTenant->name,
                        'coa_variant' => (string) ($matchedTenant->coa_variant ?? 'standard'),
                    ]
                    : null,
            ];
        }, $rows);
    }

    private function autoProvisionTenant(string $districtCode, string $name, int $jenisAkun = 5): Tenant
    {
        $code = $districtCode !== '' ? strtolower(str_replace('.', '-', $districtCode)) : Str::slug($name);
        if ($code === '') {
            $code = 'tenant';
        }

        $baseCode = $code;
        for ($suffix = 2; Tenant::query()->where('code', $code)->exists(); $suffix++) {
            $code = $baseCode.'-'.$suffix;
        }

        $variant = match ($jenisAkun) {
            7 => 'trading',
            8 => 'cooperative',
            default => 'standard',
        };

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => $code,
            'name' => $name !== '' ? $name : 'Tenant '.$districtCode,
            'district_code' => $districtCode !== '' ? $districtCode : null,
            'coa_variant' => $variant,
            'status' => 'provisioning',
            'timezone' => 'Asia/Jakarta',
        ]);

        $shard = DatabaseShard::query()->where('status', 'active')->orderBy('row_id')->first();
        if ($shard !== null) {
            TenantPlacement::query()->create([
                'tenant_id' => $tenant->row_id,
                'shard_id' => $shard->row_id,
                'status' => 'active',
                'placed_at' => now(),
            ]);
        }

        return $tenant;
    }

    public function stream(CutoverRun $run, TenantCutoverRunnerService $runner): StreamedResponse
    {
        return response()->stream(function () use ($run, $runner): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            if (function_exists('ob_implicit_flush')) {
                ob_implicit_flush(true);
            }

            $runner->observeStream($run, static function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function show(CutoverRun $run): JsonResponse
    {
        $run->load('tenant:row_id,code,name');

        return response()->json([
            'id' => $run->id,
            'tenant_id' => $run->tenant_id,
            'tenant_code' => $run->tenant_code,
            'tenant_name' => $run->tenant?->name ?? $run->tenant_code,
            'suffix' => $run->suffix,
            'is_dry_run' => $run->is_dry_run,
            'status' => $run->status,
            'steps' => $run->steps,
            'error_message' => $run->error_message,
            'output_log' => $run->output_log,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
        ]);
    }

    /**
     * AJAX endpoint that returns the cached legacy suffix discovery.
     * The first call scans the remote MySQL (slow), subsequent calls hit the cache.
     */
    public function discover(Request $request, LegacyAccountingDiscovery $discovery): JsonResponse
    {
        $force = $request->boolean('refresh');

        $cacheKey = 'admin.migration.discovery.v2';

        if ($force) {
            Cache::forget($cacheKey);
        }

        try {
            $suffixes = Cache::remember(
                $cacheKey,
                self::DISCOVERY_CACHE_TTL,
                fn () => $discovery->discover(),
            );
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'suffixes' => [],
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'count' => count($suffixes),
            'cached_until' => now()->addSeconds(self::DISCOVERY_CACHE_TTL)->toIso8601String(),
            'suffixes' => $suffixes,
        ]);
    }
}
