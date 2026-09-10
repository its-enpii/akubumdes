<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberUserLink;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class PortalMemberTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private Member $member;

    private Member $otherMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Induk',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->member = $this->createMember('Portal Anggota', 'M0001');
        $this->otherMember = $this->createMember('Anggota Lain', 'M0002');
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_user_with_other_role_cannot_access_portal(): void
    {
        $user = $this->createUser('staff');
        $this->assignRole($user, 'viewer');

        $this->actingAs($user)->get('/portal')->assertForbidden();
    }

    public function test_active_officer_sees_fellow_group_members(): void
    {
        $group = Group::query()->create([
            'code' => 'KLP-AKTIF',
            'name' => 'Kelompok Aktif',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);
        GroupMember::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->member->row_id,
            'joined_at' => '2026-01-01',
            'status' => 'active',
        ]);
        GroupMember::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->otherMember->row_id,
            'joined_at' => '2026-01-01',
            'status' => 'active',
        ]);
        GroupOfficer::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->member->row_id,
            'position' => 'chair',
            'started_at' => '2026-01-01',
        ]);

        $user = $this->createUser('active_officer');
        $this->assignRole($user, 'anggota');
        MemberUserLink::query()->create([
            'user_row_id' => $user->row_id,
            'member_row_id' => $this->member->row_id,
        ]);

        $this->actingAs($user)->get('/portal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Index')
                ->where('officers.0.position', 'chair')
                ->where('active_groups.0.group_name', 'Kelompok Aktif')
                ->where('active_groups.0.members.0.member_number', 'M0002'));
    }

    public function test_inactive_officer_does_not_see_fellow_group_members(): void
    {
        $group = Group::query()->create([
            'code' => 'KLP-LAMA',
            'name' => 'Kelompok Lama',
            'status' => 'active',
            'organization_unit_row_id' => 1,
        ]);
        GroupMember::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->otherMember->row_id,
            'joined_at' => '2026-01-01',
            'status' => 'active',
        ]);
        GroupOfficer::query()->create([
            'group_row_id' => $group->row_id,
            'member_row_id' => $this->member->row_id,
            'position' => 'treasurer',
            'started_at' => '2025-01-01',
            'ended_at' => '2025-12-31',
        ]);

        $user = $this->createUser('inactive_officer');
        $this->assignRole($user, 'anggota');
        MemberUserLink::query()->create([
            'user_row_id' => $user->row_id,
            'member_row_id' => $this->member->row_id,
        ]);

        $this->actingAs($user)->get('/portal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Index')
                ->where('officers.0.ended_at', '2025-12-31')
                ->missing('active_groups.0'));
    }

    public function test_admin_store_and_update_manage_member_link(): void
    {
        $admin = $this->createUser('admin_user');
        app(PermissionChecker::class)->ensureSystemRoles();

        $this->actingAs($admin)->post('/access/users', [
            'name' => 'Linked Member',
            'username' => 'linked_member',
            'email' => 'linked@example.test',
            'phone' => '081400000001',
            'password' => 'password',
            'password_confirmation' => 'password',
            'status' => 'active',
            'role' => 'anggota',
            'member_row_id' => $this->member->row_id,
        ])->assertRedirect('/access/users');

        $linkedUser = User::query()->where('username', 'linked_member')->firstOrFail();
        self::assertSame($this->member->row_id, (int) MemberUserLink::query()->where('user_row_id', $linkedUser->row_id)->value('member_row_id'));

        $this->actingAs($admin)->put('/access/users/'.$linkedUser->row_id, [
            'name' => 'Linked Member',
            'username' => 'linked_member',
            'email' => 'linked@example.test',
            'phone' => '081400000001',
            'password' => '',
            'password_confirmation' => '',
            'status' => 'active',
            'role' => 'viewer',
        ])->assertRedirect('/access/users');

        self::assertFalse(MemberUserLink::query()->where('user_row_id', $linkedUser->row_id)->exists());
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => ucfirst($username),
            'email' => "{$username}@example.test",
            'username' => $username,
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    private function assignRole(User $user, string $role): void
    {
        app(PermissionChecker::class)->assignRole($user, $role);
    }

    private function createMember(string $name, string $memberNumber): Member
    {
        $person = Person::query()->create(['full_name' => $name]);

        return Member::query()->create([
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => 1,
            'member_number' => $memberNumber,
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);
    }
}
