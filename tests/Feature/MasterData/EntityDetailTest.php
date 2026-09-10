<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class EntityDetailTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private OrganizationUnit $institution;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Detail',
            'email' => 'detail@example.test',
            'username' => 'detail_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Detail',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->institution = OrganizationUnit::query()->create([
            'id' => 2,
            'code' => 'INS-1',
            'name' => 'Lembaga Mitra',
            'type' => 'other_institution',
            'parent_row_id' => 1,
            'institution_identity_number' => 'INS-001',
            'leader_name' => 'Budi',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_institution_show_renders_existing_institution(): void
    {
        $this->actingAs($this->user)
            ->get('/master-data/institutions/'.$this->institution->row_id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MasterData/Institutions/Show')
                ->where('institution.name', 'Lembaga Mitra')
            );
    }
}
