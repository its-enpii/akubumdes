<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberUserLink;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class PortalMemberController
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = $this->context->id();
        $member = Member::query()
            ->whereKey(MemberUserLink::query()
                ->where('user_row_id', (int) auth()->user()?->row_id)
                ->value('member_row_id'))
            ->with(['person:row_id,full_name', 'village:row_id,name'])
            ->first();
        abort_unless($member !== null, 404, 'Akun belum terhubung ke data anggota.');

        $officers = $this->officerRows($tenantId, (int) $member->row_id);
        $activeGroupIds = collect($officers)->where('ended_at', null)->pluck('group_row_id')->unique()->values();
        $fellowMembers = $this->fellowMembers($tenantId, $activeGroupIds, (int) $member->row_id);

        return Inertia::render('Portal/Index', [
            'profile' => [
                'name' => $member->person?->full_name,
                'member_number' => $member->member_number,
                'status' => $member->status,
                'registered_at' => $member->registered_at?->toDateString(),
                'organization_unit' => $member->village?->name,
            ],
            'officers' => $officers,
            'active_groups' => $activeGroupIds->map(fn ($groupId): array => [
                'group_name' => $officers->firstWhere('group_row_id', (int) $groupId)['group_name'] ?? '',
                'members' => $fellowMembers[$groupId] ?? [],
            ])->values()->all(),
        ]);
    }

    private function officerRows(int $tenantId, int $memberRowId): Collection
    {
        return GroupOfficer::query()
            ->where('member_row_id', $memberRowId)
            ->with('group:row_id,name')
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($officer): array => [
                'group_row_id' => (int) $officer->group_row_id,
                'group_name' => $officer->group?->name,
                'position' => $officer->position,
                'started_at' => $officer->started_at?->toDateString(),
                'ended_at' => $officer->ended_at?->toDateString(),
            ]);
    }

    private function fellowMembers(int $tenantId, Collection $groupIds, int $currentMemberId): array
    {
        if ($groupIds->isEmpty()) {
            return [];
        }

        $rows = GroupMember::query()
            ->whereIn('group_row_id', $groupIds)
            ->whereNull('left_at')
            ->with(['member.person:row_id,full_name'])
            ->get()
            ->groupBy('group_row_id');

        return $rows->map(fn ($members, $groupId) => $members
            ->filter(fn ($row): bool => (int) $row->member_row_id !== $currentMemberId)
            ->values()
            ->map(fn ($row): array => [
                'name' => $row->member?->person?->full_name,
                'member_number' => $row->member?->member_number,
                'status' => $row->member?->status,
            ])->all())->all();
    }
}
