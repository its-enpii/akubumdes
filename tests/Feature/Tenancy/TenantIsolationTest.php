<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\Services\TenantSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantIsolationTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected TenantSequenceService $sequences;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->sequences = app(TenantSequenceService::class);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_local_ids_restart_per_tenant_and_queries_are_scoped(): void
    {
        $tenantARecord = OrganizationUnit::query()->create([
            'id' => $this->sequences->next('organization_units'),
            'type' => 'village',
            'code' => 'V001',
            'name' => 'Unit A',
        ]);

        self::assertSame(1, (int) $tenantARecord->id);
        self::assertSame((int) $this->testTenant->row_id, (int) $tenantARecord->tenant_id);
        self::assertCount(1, OrganizationUnit::query()->get());

        $tenantB = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tenant-b',
            'name' => 'Tenant B',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        $placementB = TenantPlacement::query()->create([
            'tenant_id' => $tenantB->row_id,
            'shard_id' => $this->testShard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        DB::connection('tenant')->table('tenant_registry')->insert([
            'id' => $tenantB->row_id,
            'public_id' => $tenantB->public_id,
            'code' => $tenantB->code,
            'name' => $tenantB->name,
            'status' => 'active',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TenantContext::class)->clear();
        app(TenantContext::class)->initialize($tenantB, $placementB, $this->testShard);

        $tenantBRecord = OrganizationUnit::query()->create([
            'id' => $this->sequences->next('organization_units'),
            'type' => 'village',
            'code' => 'V001',
            'name' => 'Unit B',
        ]);

        self::assertSame(1, (int) $tenantBRecord->id);
        self::assertCount(1, OrganizationUnit::query()->get());
        self::assertSame('Unit B', OrganizationUnit::query()->firstOrFail()->name);
    }

    public function test_explicit_cross_tenant_insert_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        OrganizationUnit::query()->create([
            'id' => 1,
            'tenant_id' => $this->testTenant->row_id + 999,
            'type' => 'village',
            'code' => 'V002',
            'name' => 'Invalid Tenant Unit',
        ]);
    }
}
