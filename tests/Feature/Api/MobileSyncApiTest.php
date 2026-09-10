<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\Platform\TenantMembership;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\FiscalPeriodProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class MobileSyncApiTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $collector;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        app(FiscalPeriodProvisioner::class)->ensureDefaults();

        $this->collector = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Kolektor Sinkron',
            'username' => 'kolektor_sync',
            'email' => 'kolektor.sync@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'tenant_id' => $this->testTenant->row_id,
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $this->testTenant->row_id,
            'user_id' => $this->collector->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->token = $this->collector->createToken('Flutter Sync')->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_push_rejects_non_whitelisted_table(): void
    {
        $mutationUuid = (string) Str::uuid();
        $response = $this->push([[
            'mutation_uuid' => $mutationUuid,
            'table_name' => 'members',
            'operation' => 'update',
            'row_public_id' => 1,
            'payload' => ['member_number' => 'MBR-999'],
            'client_updated_at' => now()->toIso8601String(),
        ]]);

        $response->assertOk()->assertJsonPath('data.rejected.0.reason', 'invalid_mutation_or_table');
    }

    public function test_offline_mode_allows_sync_push(): void
    {
        $mutationUuid = (string) Str::uuid();

        $syncResponse = $this->withHeader('X-Client-Offline', 'true')
            ->push([['mutation_uuid' => $mutationUuid, 'table_name' => 'members', 'operation' => 'update', 'row_public_id' => 1, 'payload' => ['status' => 'active'], 'client_updated_at' => now()->toIso8601String()]]);
        $syncResponse->assertOk()->assertJsonPath('data.accepted.0', $mutationUuid);

    }

    public function test_push_rejects_outdated_client_header_before_processing_mutations(): void
    {
        config(['desktop-update.min_version' => '2.0.0']);
        $mutationUuid = (string) Str::uuid();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->withHeader('X-App-Version', '1.9.0')
            ->postJson('/api/v1/mobile/sync/push', [
                'mutations' => [['mutation_uuid' => $mutationUuid, 'table_name' => 'members', 'operation' => 'update', 'row_public_id' => 1, 'payload' => ['status' => 'active'], 'client_updated_at' => now()->toIso8601String()]],
            ])
            ->assertStatus(426)
            ->assertJsonPath('code', 'CLIENT_OUTDATED')
            ->assertJsonPath('min_supported_version', '2.0.0');

        $this->assertDatabaseMissing('sync_mutations', ['mutation_uuid' => $mutationUuid], 'tenant');
    }

    private function pull(array $query = [])
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/mobile/sync/collection'.$this->buildQuery($query));
    }

    private function push(array $mutations)
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/mobile/sync/push', ['mutations' => $mutations]);
    }

    private function buildQuery(array $query): string
    {
        return $query === [] ? '' : '?'.http_build_query($query);
    }

}
