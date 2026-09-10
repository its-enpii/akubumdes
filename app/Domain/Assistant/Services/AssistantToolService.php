<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\JournalEntryOptionResolver;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Accounting\Services\JournalReversalService;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Services\AssetService;
use App\Http\Requests\Accounting\JournalEntryRequest;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * JSON tool handlers invoked by the assistant orchestrator.
 * Always run as the resolved assistant actor + current tenant.
 */
final class AssistantToolService
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly TenantContext $context,
        private readonly JournalPostingService $journalPosting,
        private readonly JournalReversalService $journalReversal,
    ) {}

    public function dispatch(string $tool, array $params, User $actor): array
    {
        $required = config('permissions.tool_map.'.$tool);
        if (is_string($required) && $required !== '') {
            $this->permissions->denyUnless($actor, $required);
        }

        return match ($tool) {
            'search_members' => $this->searchMembers($params),
            'search_groups' => $this->searchGroups($params),
            'list_accounts' => $this->listAccounts($params),
            'search_journals' => $this->searchJournals($params),
            'search_assets' => $this->searchAssets($params),
            'get_asset' => $this->getAsset($params),
            'create_journal_entry' => $this->createJournalEntry($params, $actor),
            'reverse_journal' => $this->reverseJournal($params, $actor),
            'download_report' => $this->downloadReport($params),
            default => throw new RuntimeException("Unknown tool: {$tool}"),
        };
    }

    /**
     * Same as dispatch() but without permission check — used by Sidbm
     * handlers where permissions are already enforced upstream.
     *
    public function execute(string $tool, array $params, User $actor): array
    {
        return match ($tool) {
            'search_members' => $this->searchMembers($params),
            'search_groups' => $this->searchGroups($params),
            'list_accounts' => $this->listAccounts($params),
            'search_journals' => $this->searchJournals($params),
            'search_assets' => $this->searchAssets($params),
            'get_asset' => $this->getAsset($params),
            'create_journal_entry' => $this->createJournalEntry($params, $actor),
            'reverse_journal' => $this->reverseJournal($params, $actor),
            'download_report' => $this->downloadReport($params),
            default => throw new RuntimeException("Unknown tool: {$tool}"),
        };
    }

    /**
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function searchAssets(array $params): array
    {
        $q = trim((string) ($params['query'] ?? $params['name'] ?? $params['asset_name'] ?? ''));
        if (mb_strlen($q) < 2) {
            throw ValidationException::withMessages(['query' => 'query min 2 characters']);
        }

        $term = '%'.$q.'%';
        $status = trim((string) ($params['status'] ?? ''));
        $asOf = trim((string) ($params['as_of'] ?? ''));
        $asOfDate = $asOf !== ''
            ? CarbonImmutable::parse($asOf)->startOfDay()
            : CarbonImmutable::today();

        $query = Asset::query()
            ->with(['category:row_id,code,name'])
            ->where(function ($w) use ($term): void {
                $w->where('name', 'like', $term)->orWhere('asset_code', 'like', $term);
            });

        if ($status !== '' && isset(Asset::STATUSES[$status])) {
            $query->where('status', $status);
        }

        $rows = $query->orderBy('name')->limit(15)->get();
        $service = app(AssetService::class);

        $items = $rows->map(function (Asset $a) use ($service, $asOfDate): array {
            $calc = $service->bookValue($a, $asOfDate);

            return [
                'row_id' => (int) $a->row_id,
                'id' => (int) $a->id,
                'asset_code' => $a->asset_code,
                'name' => $a->name,
                'status' => (string) $a->status,
                'status_label' => Asset::STATUSES[(string) $a->status] ?? (string) $a->status,
                'category' => $a->category?->name,
                'purchased_at' => $a->purchased_at?->format('Y-m-d'),
                'quantity' => (int) $a->quantity,
                'unit_cost' => (float) $a->unit_cost,
                'useful_life_months' => $a->useful_life_months !== null ? (int) $a->useful_life_months : null,
                'acquisition' => $calc['acquisition'],
                'book_value' => $calc['book_value'],
                'accumulated_depreciation' => $calc['accumulated_depreciation'],
                'href' => '/accounting/assets/'.$a->row_id,
            ];
        })->all();

        $count = count($items);

        return [
            'items' => $items,
            'match_count' => $count,
            'needs_clarification' => $count !== 1,
            'as_of' => $asOfDate->toDateString(),
        ];
    }

    /**
    public function getAsset(array $params): array
    {
        $rowId = (int) ($params['asset_row_id'] ?? $params['row_id'] ?? 0);
        if ($rowId <= 0) {
            throw ValidationException::withMessages(['asset_row_id' => 'asset_row_id required']);
        }

        $asset = Asset::query()->with(['category', 'unit'])->whereKey($rowId)->first();
        if ($asset === null) {
            throw ValidationException::withMessages(['asset_row_id' => 'Inventaris tidak ditemukan']);
        }

        $asOf = trim((string) ($params['as_of'] ?? ''));
        $detail = app(AssetService::class)->detail($asset, $asOf !== '' ? $asOf : null);

        return [
            ...$detail,
            'href' => '/accounting/assets/'.$asset->row_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function searchMembers(array $params): array
    {
        $q = trim((string) ($params['query'] ?? ''));
        if (mb_strlen($q) < 2) {
            throw ValidationException::withMessages(['query' => 'query min 2 characters']);
        }

        $tenantId = $this->context->id();
        $groupQ = trim((string) ($params['group_query'] ?? $params['group_name'] ?? ''));

        $query = DB::connection('tenant')
            ->table('members as m')
            ->join('people as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'm.person_row_id')
                    ->where('p.tenant_id', '=', $tenantId);
            })
            ->where('m.tenant_id', $tenantId)
            ->whereNull('m.deleted_at')
            ->where(function ($w) use ($q): void {
                $w->where('p.full_name', 'like', '%'.$q.'%')
                    ->orWhere('p.national_identity_number', 'like', '%'.$q.'%')
                    ->orWhere('p.phone', 'like', '%'.$q.'%');
            });

        if ($groupQ !== '') {
            $query->join('group_members as gm', function ($join) use ($tenantId): void {
                $join->on('gm.member_row_id', '=', 'm.row_id')
                    ->where('gm.tenant_id', '=', $tenantId)
                    ->whereNull('gm.left_at');
            })->join('groups as g', function ($join) use ($tenantId): void {
                $join->on('g.row_id', '=', 'gm.group_row_id')
                    ->where('g.tenant_id', '=', $tenantId)
                    ->whereNull('g.deleted_at');
            })->where(function ($w) use ($groupQ): void {
                $w->where('g.name', 'like', '%'.$groupQ.'%')
                    ->orWhere('g.code', 'like', '%'.$groupQ.'%');
            });
        }

        $rows = $query
            ->orderBy('p.full_name')
            ->limit(20)
            ->get([
                'm.row_id as member_row_id',
                'm.id as member_id',
                'm.status',
                'p.full_name',
                'p.national_identity_number as nik',
                'p.phone',
            ]);

        $items = $rows->map(fn ($r): array => [
            'member_row_id' => (int) $r->member_row_id,
            'member_id' => (int) $r->member_id,
            'status' => (string) $r->status,
            'name' => (string) $r->full_name,
            'nik' => (string) ($r->nik ?? ''),
            'phone' => $r->phone ? (string) $r->phone : null,
        ])->all();

        return $this->withMatchMeta($items);
    }

    public function searchGroups(array $params): array
    {
        $q = trim((string) ($params['query'] ?? ''));
        if (mb_strlen($q) < 2) {
            throw ValidationException::withMessages(['query' => 'query min 2 characters']);
        }

        $tenantId = $this->context->id();
        $rows = DB::connection('tenant')
            ->table('groups as g')
            ->where('g.tenant_id', $tenantId)
            ->whereNull('g.deleted_at')
            ->where(function ($w) use ($q): void {
                $w->where('g.name', 'like', '%'.$q.'%')
                    ->orWhere('g.code', 'like', '%'.$q.'%');
            })
            ->orderBy('g.name')
            ->limit(20)
            ->get(['g.row_id', 'g.id', 'g.code', 'g.name', 'g.status', 'g.phone']);

        $items = $rows->map(fn ($r): array => [
            'group_row_id' => (int) $r->row_id,
            'group_id' => (int) $r->id,
            'code' => (string) ($r->code ?? ''),
            'name' => (string) $r->name,
            'status' => (string) $r->status,
            'phone' => $r->phone ? (string) $r->phone : null,
        ])->all();

        return $this->withMatchMeta($items);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function listAccounts(array $params): array
    {
        $prefix = isset($params['code_prefix']) ? trim((string) $params['code_prefix']) : '';
        $nameQ = trim((string) ($params['query'] ?? $params['name'] ?? ''));
        $cashOnly = filter_var($params['cash_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->limit(50);

        if ($prefix !== '') {
            $query->where('code', 'like', $prefix.'%');
        }
        if ($cashOnly) {
            $query->where('code', 'like', '1.1.01.%');
        }
        if ($nameQ !== '') {
            $tokens = preg_split('/\s+/', mb_strtolower($nameQ)) ?: [];
            $query->where(function ($w) use ($nameQ, $tokens): void {
                $w->where('name', 'like', '%'.$nameQ.'%')
                    ->orWhere('code', 'like', '%'.$nameQ.'%');
                foreach ($tokens as $tok) {
                    if (mb_strlen($tok) >= 2) {
                        $w->orWhere('name', 'like', '%'.$tok.'%');
                    }
                }
            });
        }

        $items = $query->get(['row_id', 'code', 'name', 'account_type', 'normal_balance'])
            ->map(fn (Account $a): array => [
                'row_id' => (int) $a->row_id,
                'code' => (string) $a->code,
                'name' => (string) $a->name,
                'account_type' => (string) $a->account_type,
                'normal_balance' => (string) $a->normal_balance,
            ])->all();

        // Rank bank name hits higher when query looks like a bank.
        if ($nameQ !== '' && $items !== []) {
            $needle = mb_strtolower($nameQ);
            usort($items, function (array $a, array $b) use ($needle): int {
                $sa = $this->accountNameScore($a['name'], $a['code'], $needle);
                $sb = $this->accountNameScore($b['name'], $b['code'], $needle);

                return $sb <=> $sa;
            });
        }

        return $this->withMatchMeta($items);
    }

    /**
     * Find posted journals for correction/duplicate handling.
     * Prefer recent + filters (date, amount, type, account name, description).
     *
     * @param  array<string, mixed>  $params
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    public function searchJournals(array $params): array
    {
        $tenantId = $this->context->id();
        $dateFrom = trim((string) ($params['date_from'] ?? $params['transaction_date'] ?? ''));
        $dateTo = trim((string) ($params['date_to'] ?? $params['transaction_date'] ?? ''));
        $type = trim((string) ($params['transaction_type'] ?? ''));
        $sourceType = trim((string) ($params['source_type'] ?? ''));
        $desc = trim((string) ($params['query'] ?? $params['description'] ?? ''));
        $amount = isset($params['amount']) ? (float) $params['amount'] : null;
        $accountQ = trim((string) ($params['account_query'] ?? ''));
        $journalId = (int) ($params['journal_row_id'] ?? $params['journal_id'] ?? 0);
        $recent = filter_var($params['recent'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $createdBy = isset($params['created_by_user_id']) ? (int) $params['created_by_user_id'] : null;
        $excludeReversed = filter_var($params['exclude_reversed'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $limit = min(30, max(1, (int) ($params['limit'] ?? 15)));

        // "barusan" / recent without date → last 48h of actor-ish activity
        if ($recent && $dateFrom === '' && $dateTo === '') {
            $dateFrom = CarbonImmutable::now()->subDays(2)->toDateString();
            $dateTo = CarbonImmutable::today()->toDateString();
        }

        $q = DB::connection('tenant')
            ->table('journal_entries as e')
            ->where('e.tenant_id', $tenantId)
            ->where('e.status', 'posted');

        if ($journalId > 0) {
            $q->where(function ($w) use ($journalId): void {
                $w->where('e.row_id', $journalId)->orWhere('e.id', $journalId);
            });
        }
        if ($dateFrom !== '') {
            $q->whereDate('e.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $q->whereDate('e.transaction_date', '<=', $dateTo);
        }
        if ($type !== '') {
            $q->where('e.transaction_type', $type);
        }
        if ($sourceType !== '') {
            $q->where('e.source_type', $sourceType);
        }
        if ($desc !== '') {
            $q->where('e.description', 'like', '%'.$desc.'%');
        }
        if ($createdBy !== null && $createdBy > 0) {
            $q->where('e.created_by_user_id', $createdBy);
        }
        if ($excludeReversed) {
            // hide originals already reversed + hide reversal entries themselves by default
            $q->whereNotExists(function ($sub) use ($tenantId): void {
                $sub->selectRaw('1')
                    ->from('journal_entries as rev')
                    ->whereColumn('rev.reversed_entry_row_id', 'e.row_id')
                    ->where('rev.tenant_id', $tenantId);
            })->whereNull('e.reversed_entry_row_id');
        }

        if ($accountQ !== '' || $amount !== null) {
            $q->whereExists(function ($sub) use ($tenantId, $accountQ, $amount): void {
                $sub->selectRaw('1')
                    ->from('journal_lines as jl')
                    ->whereColumn('jl.journal_entry_row_id', 'e.row_id')
                    ->where('jl.tenant_id', $tenantId);
                if ($accountQ !== '') {
                    $sub->join('accounts as a', function ($join) use ($tenantId): void {
                        $join->on('a.row_id', '=', 'jl.account_row_id')
                            ->where('a.tenant_id', '=', $tenantId);
                    })->where(function ($w) use ($accountQ): void {
                        $w->where('a.name', 'like', '%'.$accountQ.'%')
                            ->orWhere('a.code', 'like', '%'.$accountQ.'%');
                    });
                }
                if ($amount !== null) {
                    $sub->where(function ($w) use ($amount): void {
                        $w->where('jl.debit', $amount)->orWhere('jl.credit', $amount);
                    });
                }
            });
        }

        // Default window if nothing specified: last 7 days (avoid dumping entire ledger)
        if ($journalId <= 0 && $dateFrom === '' && $dateTo === '' && $desc === '' && $amount === null && $accountQ === '' && $type === '') {
            $q->whereDate('e.transaction_date', '>=', CarbonImmutable::today()->subDays(7)->toDateString());
        }

        $rows = $q->orderByDesc('e.transaction_date')
            ->orderByDesc('e.row_id')
            ->limit($limit)
            ->get([
                'e.row_id',
                'e.id',
                'e.journal_number',
                'e.transaction_date',
                'e.transaction_type',
                'e.source_type',
                'e.source_row_id',
                'e.description',
                'e.status',
                'e.created_by_user_id',
                'e.reversed_entry_row_id',
                'e.posted_at',
            ]);

        $items = [];
        foreach ($rows as $r) {
            $lines = DB::connection('tenant')
                ->table('journal_lines as jl')
                ->join('accounts as a', function ($join) use ($tenantId): void {
                    $join->on('a.row_id', '=', 'jl.account_row_id')->where('a.tenant_id', '=', $tenantId);
                })
                ->where('jl.tenant_id', $tenantId)
                ->where('jl.journal_entry_row_id', $r->row_id)
                ->orderBy('jl.line_number')
                ->get(['a.code', 'a.name', 'a.row_id as account_row_id', 'jl.debit', 'jl.credit']);

            $totalDebit = round((float) $lines->sum('debit'), 2);
            $sourceRowId = isset($r->source_row_id) ? (int) $r->source_row_id : 0;
            // re-fetch source_row_id if not in select — add below in query
            $item = [
                'journal_row_id' => (int) $r->row_id,
                'journal_id' => (int) $r->id,
                'journal_number' => $r->journal_number,
                'transaction_date' => $r->transaction_date ? (string) $r->transaction_date : null,
                'transaction_type' => $r->transaction_type,
                'source_type' => $r->source_type,
                'source_row_id' => $sourceRowId > 0 ? $sourceRowId : null,
                'description' => (string) ($r->description ?? ''),
                'status' => (string) $r->status,
                'amount' => $totalDebit,
                'created_by_user_id' => $r->created_by_user_id ? (int) $r->created_by_user_id : null,
                'posted_at' => $r->posted_at ? (string) $r->posted_at : null,
                'already_reversed' => $r->reversed_entry_row_id !== null,
                'lines' => $lines->map(fn ($l): array => [
                    'account_code' => (string) $l->code,
                    'account_name' => (string) $l->name,
                    'debit' => (float) $l->debit,
                    'credit' => (float) $l->credit,
                ])->all(),
            ];

            $items[] = $item;
        }

        // Detect possible duplicates: same date+amount+type among results
        if (count($items) >= 2) {
            $finger = [];
            foreach ($items as $i => $it) {
                $key = ($it['transaction_date'] ?? '').'|'.$it['amount'].'|'.($it['transaction_type'] ?? '').'|'.($it['source_type'] ?? '');
                $finger[$key][] = $i;
            }
            foreach ($finger as $idxs) {
                if (count($idxs) < 2) {
                    continue;
                }
                foreach ($idxs as $i) {
                    $items[$i]['possible_duplicate_of'] = array_values(array_map(
                        static fn (int $j): int => $items[$j]['journal_row_id'],
                        array_filter($idxs, static fn (int $j): bool => $j !== $i),
                    ));
                }
            }
        }

        return $this->withMatchMeta($items);
    }

    /**
     * Reverse a posted journal (immutable ledger). Optionally re-post a corrected entry.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function reverseJournal(array $params, User $actor): array
    {
        $resolved = $this->resolveJournalForReverse($params);
        /** @var JournalEntry $original */
        $original = $resolved['entry'];
        $reversalDate = (string) ($params['reversal_date'] ?? $params['transaction_date'] ?? CarbonImmutable::today()->toDateString());
        $reason = trim((string) ($params['reason'] ?? $params['description'] ?? ''));
        if ($reason === '') {
            $reason = sprintf('Pembatalan/koreksi jurnal #%s', $original->id ?? $original->row_id);
        }

        $willRepostGeneral = filter_var($params['repost'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || isset($params['correct_bank_account_query'])
            || isset($params['correct_debit_account_row_id']);

        if (! $this->isConfirmed($params)) {
            $original->loadMissing('lines.account');
            $amount = round((float) $original->lines->sum('debit'), 2);
            $warnings = [];
            if ($willRepostGeneral && empty($params['correct_bank_account_query']) && empty($params['correct_debit_account_row_id'])) {
                $warnings[] = 'Repost diminta; pastikan akun koreksi sudah dipilih.';
            }

            return $this->previewResponse(
                action: 'reverse_journal',
                summary: sprintf(
                    'Batalkan jurnal #%s (%s) Rp %s tgl %s%s%s',
                    $original->id ?? $original->row_id,
                    $original->transaction_type ?? $original->source_type ?? 'jurnal',
                    number_format($amount, 0, ',', '.'),
                    $original->transaction_date?->toDateString() ?? '?',
                    $willRepostGeneral ? ' lalu post jurnal pengganti' : ' (tanpa post ulang)',
                ),
                plan: [
                    'journal_row_id' => (int) $original->row_id,
                    'reversal_date' => $reversalDate,
                    'reason' => $reason,
                    'amount' => $amount,
                    'repost_general' => $willRepostGeneral,
                    'correct_bank_account_query' => $params['correct_bank_account_query'] ?? null,
                    'lines' => $original->lines->map(fn ($l): array => [
                        'account_code' => $l->account?->code,
                        'account_name' => $l->account?->name,
                        'debit' => (float) $l->debit,
                        'credit' => (float) $l->credit,
                    ])->all(),
                ],
                warnings: $warnings,
                proposedParams: array_merge($params, [
                    'journal_row_id' => (int) $original->row_id,
                    'confirm' => true,
                ]),
            );
        }

        try {
            $reversal = $this->journalReversal->reverse(
                $original,
                $reversalDate,
                (int) $actor->row_id,
                $reason,
            );
        } catch (DomainException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $out = [
            'reversed' => true,
            'original' => $this->serializeJournal($original->fresh(['lines.account'])),
            'reversal' => $this->serializeJournal($reversal->load('lines.account')),
            'reason' => $reason,
        ];

        // Optional: immediately post the correct replacement
        $repost = filter_var($params['repost'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasGeneralRepost = isset($params['correct_bank_account_query'])
            || isset($params['correct_transaction_type'])
            || isset($params['correct_debit_account_row_id']);

        if ($repost || $hasGeneralRepost) {
            $correct = $this->buildCorrectedJournalParams($original, $params, $reversalDate, $reason);
            if (($correct['needs_clarification'] ?? false) === true) {
                $out['correction'] = $correct;
                $out['message'] = 'Jurnal asli sudah dibatalkan (reversal). Koreksi baru perlu klarifikasi akun.';

                return $out;
            }
            try {
                $out['correction'] = $this->createJournalEntry(array_merge($correct, ['confirm' => true]), $actor);
                $out['message'] = 'Jurnal salah dibatalkan dan diganti entri koreksi.';
            } catch (ValidationException $e) {
                $out['correction'] = [
                    'needs_clarification' => true,
                    'reason' => 'correction_validation_failed',
                    'message' => 'Reversal OK; entri koreksi gagal validasi.',
                    'messages' => $e->errors(),
                ];
            }
        } else {
            $out['message'] = 'Jurnal dibatalkan via reversal. Tidak ada entri pengganti (set repost=true untuk koreksi).';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{entry: JournalEntry}|array{needs_clarification: bool, reason: string, message: string, candidates?: list<array<string,mixed>>}
     */
    private function resolveJournalForReverse(array $params): array
    {
        $id = (int) ($params['journal_row_id'] ?? $params['journal_id'] ?? 0);
        if ($id > 0) {
            $entry = JournalEntry::query()->with('lines.account')->where('row_id', $id)->first()
                ?? JournalEntry::query()->with('lines.account')->where('id', $id)->first();
            if ($entry === null) {
                return [
                    'needs_clarification' => true,
                    'reason' => 'journal_not_found',
                    'message' => "Jurnal {$id} tidak ditemukan.",
                    'candidates' => [],
                ];
            }
            if ($entry->status !== 'posted') {
                return [
                    'needs_clarification' => true,
                    'reason' => 'not_posted',
                    'message' => 'Hanya jurnal posted yang bisa di-reversal.',
                    'candidates' => [],
                ];
            }

            return ['entry' => $entry];
        }

        $searchParams = array_merge($params, [
            'exclude_reversed' => true,
            'recent' => filter_var($params['recent'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
        // map wrong-account hint → account_query
        if (empty($searchParams['account_query']) && ! empty($params['wrong_account_query'])) {
            $searchParams['account_query'] = $params['wrong_account_query'];
        }
        $found = $this->searchJournals($searchParams);
        if ($found['match_count'] === 0) {
            return [
                'needs_clarification' => true,
                'reason' => 'journal_not_found',
                'message' => 'Jurnal tidak ditemukan. Sebutkan tanggal/nominal/akun atau journal_row_id.',
                'candidates' => [],
            ];
        }
        if ($found['match_count'] > 1) {
            // If possible_duplicate clusters of size 2 with identical fingerprint and user said duplicate, still clarify which to keep
            return [
                'needs_clarification' => true,
                'reason' => 'ambiguous_journal',
                'message' => 'Beberapa jurnal cocok. Pilih journal_row_id yang akan di-reversal (untuk duplikat: batalkan yang salah, biarkan yang benar).',
                'candidates' => $found['items'],
                'match_count' => $found['match_count'],
            ];
        }

        $entry = JournalEntry::query()
            ->with('lines.account')
            ->where('row_id', $found['items'][0]['journal_row_id'])
            ->firstOrFail();

        return ['entry' => $entry];
    }

    /**
     * Build create_journal_entry params for a correction after reverse.
     * Typical: wrong bank Ops → correct bank SPP (same amount, swap debit account).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildCorrectedJournalParams(JournalEntry $original, array $params, string $date, string $reason): array
    {
        $original->loadMissing('lines.account');
        $amount = isset($params['correct_amount'])
            ? (float) $params['correct_amount']
            : (isset($params['amount']) ? (float) $params['amount'] : (float) $original->lines->sum('debit'));

        $type = (string) ($params['correct_transaction_type']
            ?? $params['transaction_type']
            ?? $original->transaction_type
            ?? 'pemindahan_saldo');

        $desc = trim((string) ($params['correct_description'] ?? ''));
        if ($desc === '') {
            $desc = 'Koreksi: '.$reason;
            if ($original->description) {
                $desc .= ' (asli: '.$original->description.')';
            }
        }

        $out = [
            'transaction_date' => $date,
            'transaction_type' => $type,
            'amount' => $amount,
            'description' => mb_substr($desc, 0, 500),
            'reference' => 'koreksi-of-'.$original->row_id,
        ];

        if (! empty($params['correct_debit_account_row_id'])) {
            $out['debit_account_row_id'] = (int) $params['correct_debit_account_row_id'];
        } elseif (! empty($params['correct_bank_account_query']) || ! empty($params['correct_account_query'])) {
            $hint = (string) ($params['correct_bank_account_query'] ?? $params['correct_account_query']);
            if ($type === 'pemindahan_saldo' || str_contains(mb_strtolower($hint), 'bank')) {
                $bank = $this->resolveBankAccount($hint);
                if (isset($bank['needs_clarification'])) {
                    return $bank;
                }
                $out['debit_account_row_id'] = $bank['row_id'];
            } else {
                $list = $this->listAccounts(['query' => $hint]);
                if ($list['match_count'] !== 1) {
                    return [
                        'needs_clarification' => true,
                        'reason' => 'ambiguous_correct_account',
                        'message' => 'Akun koreksi ambigu. Pilih correct_debit_account_row_id.',
                        'candidates' => $list['items'],
                    ];
                }
                $out['debit_account_row_id'] = $list['items'][0]['row_id'];
            }
        }

        if (! empty($params['correct_credit_account_row_id'])) {
            $out['credit_account_row_id'] = (int) $params['correct_credit_account_row_id'];
        } elseif (! empty($params['correct_cash_account_query'])) {
            $cash = $this->resolveCashAccount((string) $params['correct_cash_account_query']);
            if (isset($cash['needs_clarification'])) {
                return $cash;
            }
            $out['credit_account_row_id'] = $cash['row_id'];
        } else {
            // default: reuse original credit line (usually Kas Tunai for setor)
            $creditLine = $original->lines->first(static fn ($l) => (float) $l->credit > 0);
            if ($creditLine) {
                $out['credit_account_row_id'] = (int) $creditLine->account_row_id;
            }
        }

        // If debit still missing, try copy non-matching from original debit
        if (empty($out['debit_account_row_id'])) {
            $debitLine = $original->lines->first(static fn ($l) => (float) $l->debit > 0);
            if ($debitLine) {
                $out['debit_account_row_id'] = (int) $debitLine->account_row_id;
            }
        }

        return $out;
    }

    /**
     * General journal → posted.
     *
     * Supports pembelian_aset_* (creates Asset) — same rules as UI:
     * debit = 1.2.01.0x per kind, credit = kas (1.1.01.*).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createJournalEntry(array $params, User $actor): array
    {
        $tenantId = $this->context->id();
        $types = JournalEntryRequest::TRANSACTION_TYPES;
        $accountExists = Rule::exists(Account::class, 'row_id')
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('is_active', true)->where('is_postable', true));

        // Alias UI field names → debit/credit.
        if (! isset($params['debit_account_row_id']) && isset($params['disimpan_ke_row_id'])) {
            $params['debit_account_row_id'] = $params['disimpan_ke_row_id'];
        }
        if (! isset($params['credit_account_row_id']) && isset($params['sumber_dana_row_id'])) {
            $params['credit_account_row_id'] = $params['sumber_dana_row_id'];
        }

        $hintName = trim((string) ($params['asset_name'] ?? $params['description'] ?? ''));
        $type = (string) ($params['transaction_type'] ?? '');
        if ($type === '' && $hintName !== '' && $this->looksLikeInventoryPurchase($hintName, $params)) {
            $code = $this->suggestInventoryAccountCode($hintName !== '' ? $hintName : (string) ($params['asset_name'] ?? ''));
            $type = match ($code) {
                '1.2.01.01' => 'pembelian_aset_tanah',
                '1.2.01.02' => 'pembelian_aset_gedung',
                '1.2.01.03' => 'pembelian_aset_kendaraan',
                '1.2.03.01' => 'pembelian_biaya_pendirian',
                '1.2.03.02' => 'pembelian_lisensi',
                '1.2.03.03' => 'pembelian_sewa_dibayar_dimuka',
                '1.2.03.04' => 'pembelian_asuransi_dibayar_dimuka',
                default => 'pembelian_aset_peralatan',
            };
            $params['transaction_type'] = $type;
        }
        if ($type === '' && $this->looksLikeCashTransfer($hintName, $params)) {
            $type = 'pemindahan_saldo';
            $params['transaction_type'] = $type;
        }
        if ($type === '' && ! empty($params['debit_account_row_id']) && ! empty($params['credit_account_row_id'])) {
            $type = 'pemindahan_saldo';
            $params['transaction_type'] = $type;
        }
        if (empty($params['description'])) {
            $debitName = ! empty($params['debit_account_row_id'])
                ? Account::query()->where('row_id', (int) $params['debit_account_row_id'])->value('name')
                : null;
            $creditName = ! empty($params['credit_account_row_id'])
                ? Account::query()->where('row_id', (int) $params['credit_account_row_id'])->value('name')
                : null;
            if ($debitName && $creditName) {
                $params['description'] = sprintf('Pemindahan saldo dari %s ke %s', $creditName, $debitName);
            } else {
                $params['description'] = 'Pencatatan jurnal transaksi';
            }
        }
        $isInventory = JournalEntryOptionResolver::isAssetPurchase($type);
        $isTransfer = $type === 'pemindahan_saldo';

        if ($isInventory) {
            $params = $this->normalizeInventoryParams($params, $hintName);
        } elseif ($isTransfer) {
            $params = $this->normalizeTransferParams($params, $hintName);
            if (($params['needs_clarification'] ?? false) === true) {
                return $params;
            }
        }

        // Soft-validate for preview without posting.
        if (! $this->isConfirmed($params)) {
            $previewErrors = [];
            foreach (['transaction_date', 'amount'] as $req) {
                if (empty($params[$req])) {
                    $previewErrors[$req] = ["{$req} wajib untuk preview"];
                }
            }
            if ($type === '') {
                $previewErrors['transaction_type'] = ['Jenis transaksi tidak jelas (setor bank / beli inventaris / …)'];
            }
            if ($previewErrors !== []) {
                return [
                    'needs_clarification' => true,
                    'reason' => 'incomplete_journal',
                    'message' => 'Data jurnal belum lengkap. Lengkapi atau pilih opsi.',
                    'messages' => $previewErrors,
                ];
            }
            $debit = ! empty($params['debit_account_row_id'])
                ? Account::query()->where('row_id', (int) $params['debit_account_row_id'])->first(['row_id', 'code', 'name'])
                : null;
            $credit = ! empty($params['credit_account_row_id'])
                ? Account::query()->where('row_id', (int) $params['credit_account_row_id'])->first(['row_id', 'code', 'name'])
                : null;

            return $this->previewResponse(
                action: 'create_journal_entry',
                summary: sprintf(
                    '%s Rp %s tgl %s — Dr %s / Cr %s',
                    $type !== '' ? $type : 'jurnal',
                    number_format((float) $params['amount'], 0, ',', '.'),
                    (string) $params['transaction_date'],
                    $debit ? "{$debit->code} {$debit->name}" : '?',
                    $credit ? "{$credit->code} {$credit->name}" : '?',
                ),
                plan: [
                    'transaction_date' => $params['transaction_date'] ?? null,
                    'transaction_type' => $type,
                    'amount' => isset($params['amount']) ? (float) $params['amount'] : null,
                    'description' => $params['description'] ?? null,
                    'debit' => $debit ? ['row_id' => (int) $debit->row_id, 'code' => $debit->code, 'name' => $debit->name] : null,
                    'credit' => $credit ? ['row_id' => (int) $credit->row_id, 'code' => $credit->code, 'name' => $credit->name] : null,
                    'inventory' => $isInventory ? [
                        'asset_name' => $params['asset_name'] ?? null,
                        'qty' => $params['asset_quantity'] ?? null,
                        'unit_cost' => $params['asset_unit_cost'] ?? null,
                    ] : null,
                ],
                warnings: array_values(array_filter([
                    ($debit === null) ? 'Akun debit belum ter-resolve' : null,
                    ($credit === null) ? 'Akun kredit belum ter-resolve' : null,
                    empty($params['description']) && ! $isInventory ? 'Deskripsi kosong' : null,
                ])),
                proposedParams: array_merge($params, ['confirm' => true]),
            );
        }

        $data = Validator::make($params, [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_type' => ['required', Rule::in($types)],
            'description' => [$isInventory ? 'nullable' : 'required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'debit_account_row_id' => ['required', 'integer', $accountExists],
            'credit_account_row_id' => ['required', 'integer', 'different:debit_account_row_id', $accountExists],
            'asset_name' => [$isInventory ? 'required' : 'nullable', 'string', 'max:180'],
            'asset_quantity' => [$isInventory ? 'required' : 'nullable', 'integer', 'min:1', 'max:999999'],
            'asset_unit_cost' => [$isInventory ? 'required' : 'nullable', 'numeric', 'min:1'],
            'asset_useful_life_months' => [
                $isInventory ? 'required' : 'nullable',
                'integer',
                ($type === 'pembelian_aset_tanah') ? 'min:0' : 'min:0',
                'max:1200',
            ],
        ], [], [
            'debit_account_row_id' => 'akun debit (disimpan ke / inventaris)',
            'credit_account_row_id' => 'akun kredit (sumber dana / kas)',
            'asset_name' => 'nama barang',
            'asset_quantity' => 'jumlah unit',
            'asset_unit_cost' => 'harga satuan',
            'asset_useful_life_months' => 'umur ekonomis (bulan)',
        ])->validate();

        if ($isInventory) {
            $qty = (int) $data['asset_quantity'];
            $unit = (float) $data['asset_unit_cost'];
            $expected = round($qty * $unit, 2);
            $amount = round((float) $data['amount'], 2);
            if ($amount !== $expected) {
                // Prefer qty×unit as source of truth for inventory.
                $data['amount'] = $expected;
            }
            $name = trim((string) $data['asset_name']);
            $data['description'] = trim((string) ($data['description'] ?? '')) !== ''
                ? (string) $data['description']
                : sprintf('Pembelian inventaris: %s (%d unit)', $name, $qty);
        }

        $userId = (int) $actor->row_id;

        $entry = DB::connection('tenant')->transaction(function () use ($data, $userId, $isInventory): JournalEntry {
            $entry = JournalEntry::query()->create([
                'journal_number' => null,
                'transaction_date' => $data['transaction_date'],
                'sequence_number' => 0,
                'source_type' => $isInventory ? 'asset_purchase' : 'manual',
                'transaction_type' => $data['transaction_type'],
                'source_row_id' => null,
                'description' => $data['description'],
                'legacy_relation' => $data['reference'] ?? null,
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            $entry->lines()->create([
                'line_number' => 1,
                'account_row_id' => (int) $data['debit_account_row_id'],
                'organization_unit_row_id' => null,
                'description' => $data['description'],
                'debit' => (float) $data['amount'],
                'credit' => 0,
            ]);

            $entry->lines()->create([
                'line_number' => 2,
                'account_row_id' => (int) $data['credit_account_row_id'],
                'organization_unit_row_id' => null,
                'description' => $data['description'],
                'debit' => 0,
                'credit' => (float) $data['amount'],
            ]);

            if ($isInventory) {
                $asset = app(AssetService::class)->create([
                    'name' => (string) $data['asset_name'],
                    'purchased_at' => $data['transaction_date'],
                    'quantity' => (int) $data['asset_quantity'],
                    'unit_cost' => (float) $data['asset_unit_cost'],
                    'useful_life_months' => (int) ($data['asset_useful_life_months'] ?? 0),
                    'status' => 'good',
                    'category_code' => JournalEntryOptionResolver::ATB_PURCHASE_TYPES[$data['transaction_type']] ?? null,
                ], $userId);
                $entry->update(['source_row_id' => (int) $asset->row_id]);
            }

            return $entry->fresh(['lines.account']);
        });

        $posted = $this->journalPosting->post($entry, $userId);
        $result = $this->serializeJournal($posted);
        if ($isInventory) {
            $result['inventory'] = [
                'asset_row_id' => (int) $posted->source_row_id,
                'asset_name' => (string) $data['asset_name'],
                'quantity' => (int) $data['asset_quantity'],
                'unit_cost' => (float) $data['asset_unit_cost'],
                'useful_life_months' => (int) $data['asset_useful_life_months'],
                'inferred' => [
                    'debit_account' => Account::query()->where('row_id', (int) $data['debit_account_row_id'])->value('code'),
                    'credit_account' => Account::query()->where('row_id', (int) $data['credit_account_row_id'])->value('code'),
                ],
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function looksLikeInventoryPurchase(string $text, array $params): bool
    {
        if (isset($params['asset_name']) && trim((string) $params['asset_name']) !== '') {
            return true;
        }
        $t = mb_strtolower($text);
        foreach (['beli', 'membeli', 'pembelian', 'inventaris', 'aset tetap', 'aset tak berwujud', 'lisensi', 'sewa', 'asuransi', 'motor', 'mobil', 'kendaraan', 'laptop', 'komputer', 'meja', 'kursi', 'printer', 'mesin'] as $kw) {
            if (str_contains($t, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fill inventory defaults + resolve accounts when LLM omits row ids.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function normalizeInventoryParams(array $params, string $hintName): array
    {
        $name = trim((string) ($params['asset_name'] ?? ''));
        if ($name === '') {
            // "beli sepeda motor kemarin" → strip leading beli/membeli
            $name = trim((string) preg_replace(
                '/^(saya\s+)?(kemarin\s+)?(telah\s+)?(sudah\s+)?(membeli|beli|pembelian)\s+/iu',
                '',
                $hintName,
            ));
            $name = trim((string) preg_replace('/\s+(kemarin|hari\s+ini|tadi).*$/iu', '', $name));
            if ($name === '') {
                $name = $hintName !== '' ? $hintName : 'Inventaris';
            }
            $params['asset_name'] = mb_substr($name, 0, 180);
        }

        $qty = max(1, (int) ($params['asset_quantity'] ?? 1));
        $params['asset_quantity'] = $qty;

        $unit = isset($params['asset_unit_cost']) ? (float) $params['asset_unit_cost'] : 0.0;
        $amount = isset($params['amount']) ? (float) $params['amount'] : 0.0;
        if ($unit <= 0 && $amount > 0) {
            $unit = round($amount / $qty, 2);
        }
        if ($amount <= 0 && $unit > 0) {
            $amount = round($unit * $qty, 2);
        }
        if ($unit > 0) {
            $params['asset_unit_cost'] = $unit;
        }
        if ($amount > 0) {
            $params['amount'] = $amount;
        }

        if (! isset($params['asset_useful_life_months']) || $params['asset_useful_life_months'] === '') {
            $params['asset_useful_life_months'] = $this->defaultUsefulLifeMonths((string) $params['asset_name']);
        }

        // transaction_date: required Y-m-d from the LLM (orchestrator resolves
        // "kemarin" / "minggu lalu" using today's date in the embed system prompt).

        // Resolve debit (inventaris) / credit (kas) if missing.
        if (empty($params['debit_account_row_id'])) {
            $code = $this->suggestInventoryAccountCode((string) $params['asset_name']);
            $isIntangible = str_starts_with($code, '1.2.03.');
            $params['debit_account_row_id'] = $this->findPostableAccountRowId($code, $isIntangible ? '1.2.03.' : '1.2.01.');
        }
        if (empty($params['credit_account_row_id'])) {
            $cashCode = (string) ($params['cash_account_code'] ?? '1.1.01.01');
            $params['credit_account_row_id'] = $this->findPostableAccountRowId($cashCode, '1.1.01.');
        }

        if (empty($params['description'])) {
            $params['description'] = sprintf(
                'Pembelian inventaris: %s (%d unit)',
                $params['asset_name'],
                $params['asset_quantity'],
            );
        }

        return $params;
    }

    private function defaultUsefulLifeMonths(string $assetName): int
    {
        $t = mb_strtolower($assetName);
        // Kendaraan & mesin — COA 1.2.01.03, umum 4–5 tahun.
        foreach (['motor', 'mobil', 'kendaraan', 'truk', 'pick up', 'pickup', 'sepeda motor', 'mesin'] as $kw) {
            if (str_contains($t, $kw)) {
                return 48;
            }
        }
        if (str_contains($t, 'gedung') || str_contains($t, 'bangunan')) {
            return 240;
        }
        if (str_contains($t, 'tanah')) {
            return 0; // non-depreciating
        }
        foreach (['lisensi', 'sewa', 'asuransi', 'hak pakai', 'aset tak berwujud'] as $kw) {
            if (str_contains($t, $kw)) {
                return 60;
            }
        }
        // Inventaris/peralatan & elektronik
        foreach (['laptop', 'komputer', 'printer', 'monitor', 'hp', 'handphone'] as $kw) {
            if (str_contains($t, $kw)) {
                return 36;
            }
        }

        return 60; // default inventaris/peralatan
    }

    private function suggestInventoryAccountCode(string $assetName): string
    {
        $t = mb_strtolower($assetName);
        if (str_contains($t, 'pendirian')) {
            return '1.2.03.01';
        }
        if (str_contains($t, 'lisensi')) {
            return '1.2.03.02';
        }
        if (str_contains($t, 'sewa')) {
            return '1.2.03.03';
        }
        if (str_contains($t, 'asuransi')) {
            return '1.2.03.04';
        }
        if (str_contains($t, 'tanah')) {
            return '1.2.01.01';
        }
        if (str_contains($t, 'gedung') || str_contains($t, 'bangunan')) {
            return '1.2.01.02';
        }
        foreach (['motor', 'mobil', 'kendaraan', 'truk', 'mesin', 'sepeda motor'] as $kw) {
            if (str_contains($t, $kw)) {
                return '1.2.01.03'; // Kendaraan dan Mesin
            }
        }

        return '1.2.01.04'; // Inventaris/Peralatan
    }

    private function findPostableAccountRowId(string $preferredCode, string $fallbackPrefix): int
    {
        $exact = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', $preferredCode)
            ->value('row_id');
        if ($exact !== null) {
            return (int) $exact;
        }

        $fallback = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', 'like', $fallbackPrefix.'%')
            ->orderBy('code')
            ->value('row_id');
        if ($fallback === null) {
            throw ValidationException::withMessages([
                'debit_account_row_id' => "Akun postable dengan prefix {$fallbackPrefix} tidak ditemukan.",
            ]);
        }

        return (int) $fallback;
    }

    /**
     * @return array{row_id: int}|array{needs_clarification: bool, reason: string, message: string, candidates: list<array<string,mixed>>}
     */
    private function resolveCashAccount(string $hint): array
    {
        $list = $this->listAccounts([
            'query' => $hint,
            'cash_only' => true,
        ]);
        if ($list['match_count'] === 1) {
            return ['row_id' => $list['items'][0]['row_id']];
        }
        if ($list['match_count'] > 1) {
            // prefer exact-ish: "Kas Tunai" default when hint generic
            $lower = mb_strtolower($hint);
            foreach ($list['items'] as $item) {
                if (mb_strtolower($item['name']) === $lower || mb_strtolower($item['code']) === $lower) {
                    return ['row_id' => $item['row_id']];
                }
            }
            if (str_contains($lower, 'tunai') || $lower === 'kas' || $lower === 'kas tunai') {
                foreach ($list['items'] as $item) {
                    if (str_contains(mb_strtolower($item['name']), 'tunai') || $item['code'] === '1.1.01.01') {
                        return ['row_id' => $item['row_id']];
                    }
                }
            }

            return [
                'needs_clarification' => true,
                'reason' => 'ambiguous_cash_account',
                'message' => 'Beberapa akun kas/bank cocok. Pilih cash_account_row_id.',
                'candidates' => $list['items'],
            ];
        }

        // fallback first cash
        $fallback = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', 'like', '1.1.01.%')
            ->orderBy('code')
            ->first();
        if ($fallback === null) {
            return [
                'needs_clarification' => true,
                'reason' => 'cash_account_missing',
                'message' => 'Tidak ada akun kas postable (1.1.01.*).',
                'candidates' => [],
            ];
        }

        return ['row_id' => (int) $fallback->row_id];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function looksLikeCashTransfer(string $text, array $params): bool
    {
        if (isset($params['bank_account_query']) || isset($params['bank_name']) || isset($params['to_account_query'])) {
            return true;
        }
        $t = mb_strtolower($text);
        foreach (['setor', 'stor', 'transfer', 'pindah saldo', 'pemindahan', 'ke bank', 'ke rekening'] as $kw) {
            if (str_contains($t, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kas/bank transfer: Dr bank, Cr kas tunai (setor ke bank).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function normalizeTransferParams(array $params, string $hintName): array
    {
        $bankHint = trim((string) (
            $params['bank_account_query']
            ?? $params['bank_name']
            ?? $params['to_account_query']
            ?? $params['debit_account_query']
            ?? ''
        ));
        if ($bankHint === '' && $hintName !== '') {
            if (preg_match('/\b(?:bank|rekening)\s+([a-z0-9 ._-]{2,40})/iu', $hintName, $m)) {
                $bankHint = trim($m[0]);
            } elseif (preg_match('/\b(?:ke|ke\s+bank)\s+([a-z0-9 ._-]{2,40})/iu', $hintName, $m)) {
                $bankHint = trim($m[1]);
            }
        }

        $cashHint = trim((string) (
            $params['cash_account_query']
            ?? $params['from_account_query']
            ?? $params['credit_account_query']
            ?? 'Kas Tunai'
        ));

        if (empty($params['debit_account_row_id'])) {
            $bank = $this->resolveBankAccount($bankHint !== '' ? $bankHint : 'Bank');
            if (isset($bank['needs_clarification'])) {
                return $bank;
            }
            $params['debit_account_row_id'] = $bank['row_id'];
        }

        if (empty($params['credit_account_row_id'])) {
            $cash = $this->resolveCashAccount($cashHint);
            if (isset($cash['needs_clarification'])) {
                return $cash;
            }
            $params['credit_account_row_id'] = $cash['row_id'];
        }

        if (empty($params['description'])) {
            $params['description'] = $hintName !== ''
                ? mb_substr($hintName, 0, 500)
                : 'Pemindahan saldo kas ke bank';
        }

        return $params;
    }

    /**
     * Bank accounts = postable 1.1.01.03–1.1.01.99 (tunai=01, kecil=02).
     * Match only against real COA names/codes for this tenant — no hard-coded bank brands.
     *
     * @return array{row_id: int}|array{needs_clarification: bool, reason: string, message: string, candidates: list<array<string,mixed>>}
     */
    private function resolveBankAccount(string $hint): array
    {
        $allCash = $this->listAccounts([
            'code_prefix' => '1.1.01.',
            'cash_only' => true,
        ]);

        $pool = array_values(array_filter(
            $allCash['items'],
            static function (array $a): bool {
                // 1.1.01.01 kas tunai, 1.1.01.02 kas kecil → not bank
                if (preg_match('/^1\.1\.01\.(\d+)$/', $a['code'], $m)) {
                    return (int) $m[1] >= 3;
                }

                return str_contains(mb_strtolower($a['name']), 'bank');
            },
        ));

        if ($pool === []) {
            return [
                'needs_clarification' => true,
                'reason' => 'bank_account_missing',
                'message' => 'Tidak ada akun bank (1.1.01.03+). Sebutkan kode/nama akun tujuan.',
                'candidates' => $allCash['items'],
            ];
        }

        $needle = mb_strtolower(trim($hint));
        if ($needle !== '') {
            usort($pool, function (array $a, array $b) use ($needle): int {
                return $this->accountNameScore($b['name'], $b['code'], $needle)
                    <=> $this->accountNameScore($a['name'], $a['code'], $needle);
            });
            $best = $this->accountNameScore($pool[0]['name'], $pool[0]['code'], $needle);
            $second = isset($pool[1]) ? $this->accountNameScore($pool[1]['name'], $pool[1]['code'], $needle) : -1;
            if ($best > 0 && $best > $second) {
                return ['row_id' => $pool[0]['row_id']];
            }
            // filter to positive scores only when hint given
            $scored = array_values(array_filter(
                $pool,
                fn (array $a): bool => $this->accountNameScore($a['name'], $a['code'], $needle) > 0,
            ));
            if (count($scored) === 1) {
                return ['row_id' => $scored[0]['row_id']];
            }
            if (count($scored) > 1) {
                $pool = $scored;
            }
        }

        if (count($pool) === 1) {
            return ['row_id' => $pool[0]['row_id']];
        }

        return [
            'needs_clarification' => true,
            'reason' => 'ambiguous_bank_account',
            'message' => 'Beberapa akun bank cocok. Pilih debit_account_row_id dari daftar (nama akun tenant).',
            'candidates' => $pool,
        ];
    }

    /**
     * @return list<array{member_row_id: int, name: string, status: string}>
     */
    private function groupMemberItems(int $groupRowId): array
    {
        if ($groupRowId <= 0) {
            return [];
        }
        $tenantId = $this->context->id();

        return DB::connection('tenant')
            ->table('group_members as gm')
            ->join('members as m', function ($join) use ($tenantId): void {
                $join->on('m.row_id', '=', 'gm.member_row_id')->where('m.tenant_id', '=', $tenantId);
            })
            ->join('people as p', function ($join) use ($tenantId): void {
                $join->on('p.row_id', '=', 'm.person_row_id')->where('p.tenant_id', '=', $tenantId);
            })
            ->where('gm.tenant_id', $tenantId)
            ->where('gm.group_row_id', $groupRowId)
            ->whereNull('gm.left_at')
            ->whereNull('m.deleted_at')
            ->orderBy('p.full_name')
            ->limit(50)
            ->get(['m.row_id', 'p.full_name', 'm.status'])
            ->map(fn ($r): array => [
                'member_row_id' => (int) $r->row_id,
                'name' => (string) $r->full_name,
                'status' => (string) $r->status,
            ])->all();
    }

    /**
     * Score name/code against free-text hint. No tenant-specific bank aliases —
     * only actual account name/code tokens from the COA.
     */
    private function accountNameScore(string $name, string $code, string $needle): int
    {
        $n = mb_strtolower($name);
        $c = mb_strtolower($code);
        $needle = mb_strtolower(trim($needle));
        if ($needle === '') {
            return 0;
        }
        $score = 0;
        if ($n === $needle || $c === $needle) {
            $score += 100;
        }
        if (str_contains($n, $needle)) {
            $score += 40;
        }
        foreach (preg_split('/\s+/', $needle) ?: [] as $tok) {
            if (mb_strlen($tok) < 2) {
                continue;
            }
            // skip generic filler that matches every cash line
            if (in_array($tok, ['kas', 'di', 'ke', 'bank', 'rekening', 'setor', 'stor'], true)) {
                if ($tok === 'bank' && str_contains($n, 'bank')) {
                    $score += 3; // mild preference for bank-labelled accounts
                }

                continue;
            }
            if (str_contains($n, $tok)) {
                $score += 10;
            }
            if (str_contains($c, $tok)) {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{items: list<array<string, mixed>>, match_count: int, needs_clarification: bool}
     */
    private function withMatchMeta(array $items): array
    {
        $count = count($items);

        return [
            'items' => $items,
            'match_count' => $count,
            'needs_clarification' => $count !== 1,
        ];
    }

    /**
     * Write tools default to preview. Post only when confirm/confirmed/execute is true.
     *
     * @param  array<string, mixed>  $params
     */
    private function isConfirmed(array $params): bool
    {
        foreach (['confirm', 'confirmed', 'execute', 'commit'] as $key) {
            if (array_key_exists($key, $params) && filter_var($params[$key], FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Standard preview payload — asisten tampilkan ke user, tanya konfirmasi / opsi.
     *
     * @param  array<string, mixed>  $plan
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $proposedParams
     * @param  list<array{id: string, label: string}>  $options
     * @return array<string, mixed>
     */
    private function previewResponse(
        string $action,
        string $summary,
        array $plan,
        array $warnings,
        array $proposedParams,
        array $options = [],
    ): array {
        return [
            'preview' => true,
            'needs_confirmation' => true,
            'action' => $action,
            'summary' => $summary,
            'plan' => $plan,
            'warnings' => $warnings,
            'options' => $options,
            'message' => 'Ini rencana saja — belum diposting. Konfirmasi user lalu panggil ulang dengan confirm=true'
                .($options !== [] ? ' (atau pilih options[].id lewat allocation_choice).' : '.'),
            'proposed_params' => $proposedParams,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeJournal(JournalEntry $entry): array
    {
        $entry->loadMissing('lines.account');

        return [
            'journal_row_id' => (int) $entry->row_id,
            'journal_id' => (int) $entry->id,
            'journal_number' => $entry->journal_number,
            'transaction_date' => $entry->transaction_date?->toDateString(),
            'transaction_type' => $entry->transaction_type,
            'description' => $entry->description,
            'status' => $entry->status,
            'lines' => $entry->lines->map(fn ($l): array => [
                'account_code' => $l->account?->code,
                'account_name' => $l->account?->name,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
            ])->all(),
        ];
    }

    /**
     * Build direct download URL and button block for reports.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function downloadReport(array $params): array
    {
        $rawType = strtolower(trim((string) ($params['report_type'] ?? 'balance_sheet')));
        $format = strtolower(trim((string) ($params['format'] ?? 'pdf')));
        if (! in_array($format, ['pdf', 'excel'], true)) {
            $format = 'pdf';
        }

        $now = CarbonImmutable::now();
        $year = isset($params['year']) && is_numeric($params['year']) ? (int) $params['year'] : $now->year;
        $month = isset($params['month']) && is_numeric($params['month']) ? (int) $params['month'] : null;

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $periodLabel = $month && isset($monthNames[$month])
            ? "{$monthNames[$month]} {$year}"
            : "Tahun {$year}";

        // Normalize aliases
        $type = match ($rawType) {
            'neraca', 'balance_sheet' => 'balance_sheet',
            'laba_rugi', 'labarugi', 'surplus_defisit', 'income_statement' => 'income_statement',
            'arus_kas', 'cash_flow' => 'cash_flow',
            'neraca_saldo', 'trial_balance' => 'trial_balance',
            'perubahan_modal', 'perubahan_ekuitas', 'equity_change' => 'equity_change',
            'calk', 'catatan_atas_laporan_keuangan' => 'calk',
            'buku_besar', 'general_ledger' => 'general_ledger',
            'jurnal', 'jurnal_transaksi', 'journals' => 'journals',
            'kesehatan_keuangan', 'financial_health' => 'financial_health',
            'aset_tetap', 'fixed_assets', 'inventaris' => 'fixed_assets',
            'aset_takberwujud', 'intangible_assets' => 'intangible_assets',
            'anggota', 'members' => 'members',
            'kelompok', 'groups' => 'groups',
            default => 'balance_sheet',
        };

        $reportMeta = match ($type) {
            'balance_sheet' => [
                'name' => 'Laporan Neraca',
                'short_name' => 'Neraca',
                'pdf' => '/accounting/reports/balance-sheet/pdf',
                'excel' => '/accounting/reports/balance-sheet/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'income_statement' => [
                'name' => 'Laporan Laba Rugi',
                'short_name' => 'Laba Rugi',
                'pdf' => '/accounting/reports/income-statement/pdf',
                'excel' => '/accounting/reports/income-statement/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'cash_flow' => [
                'name' => 'Laporan Arus Kas',
                'short_name' => 'Arus Kas',
                'pdf' => '/accounting/reports/cash-flow/pdf',
                'excel' => '/accounting/reports/cash-flow/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'trial_balance' => [
                'name' => 'Laporan Neraca Saldo',
                'short_name' => 'Neraca Saldo',
                'pdf' => '/accounting/reports/trial-balance/pdf',
                'excel' => '/accounting/reports/trial-balance/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'equity_change' => [
                'name' => 'Laporan Perubahan Ekuitas',
                'short_name' => 'Perubahan Modal',
                'pdf' => '/accounting/reports/equity-change/pdf',
                'excel' => '/accounting/reports/equity-change/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'calk' => [
                'name' => 'Catatan Atas Laporan Keuangan (CALK)',
                'short_name' => 'CALK',
                'pdf' => '/accounting/reports/calk/pdf',
                'excel' => null,
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'general_ledger' => [
                'name' => 'Laporan Buku Besar',
                'short_name' => 'Buku Besar',
                'pdf' => '/accounting/reports/general-ledger/pdf',
                'excel' => '/accounting/reports/general-ledger/excel',
                'query' => array_filter([
                    'month' => $month,
                    'year' => $year,
                    'account' => $params['account_id'] ?? null,
                ]),
            ],
            'journals' => [
                'name' => 'Laporan Jurnal Transaksi',
                'short_name' => 'Jurnal',
                'pdf' => '/accounting/reports/journals/pdf',
                'excel' => '/accounting/reports/journals/excel',
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'financial_health' => [
                'name' => 'Analisis Kesehatan Keuangan',
                'short_name' => 'Kesehatan Keuangan',
                'pdf' => '/accounting/reports/financial-health/pdf',
                'excel' => null,
                'query' => array_filter(['month' => $month, 'year' => $year]),
            ],
            'fixed_assets' => [
                'name' => 'Daftar Aset Tetap',
                'short_name' => 'Aset Tetap',
                'pdf' => '/accounting/reports/assets/fixed/pdf',
                'excel' => '/accounting/reports/assets/fixed/excel',
                'query' => array_filter(['as_of' => $params['as_of_date'] ?? $now->toDateString()]),
            ],
            'intangible_assets' => [
                'name' => 'Daftar Aset Tidak Berwujud',
                'short_name' => 'Aset Tak Berwujud',
                'pdf' => '/accounting/reports/assets/intangible/pdf',
                'excel' => '/accounting/reports/assets/intangible/excel',
                'query' => array_filter(['as_of' => $params['as_of_date'] ?? $now->toDateString()]),
            ],
            'members' => [
                'name' => 'Ekspor Data Anggota',
                'short_name' => 'Data Anggota',
                'pdf' => null,
                'excel' => '/members/export',
                'query' => [],
            ],
            'groups' => [
                'name' => 'Ekspor Data Kelompok',
                'short_name' => 'Data Kelompok',
                'pdf' => null,
                'excel' => '/groups/export',
                'query' => [],
            ],
        };

        // Fallback format if requested format is not supported for this report
        if ($format === 'excel' && empty($reportMeta['excel'])) {
            $format = 'pdf';
        } elseif ($format === 'pdf' && empty($reportMeta['pdf'])) {
            $format = 'excel';
        }

        $queryParams = $reportMeta['query'];
        if ($format === 'pdf') {
            $queryParams['download'] = 1;
        }

        $basePath = $format === 'excel' ? $reportMeta['excel'] : $reportMeta['pdf'];
        $queryString = http_build_query($queryParams);
        $url = $basePath.($queryString !== '' ? '?'.$queryString : '');

        $reportName = $reportMeta['name'];
        $shortName = $reportMeta['short_name'] ?? $reportName;
        $formatUpper = strtoupper($format);
        $btnLabel = "Unduh {$shortName} ({$formatUpper})";
        $actionButton = sprintf('::button{"label":"%s","url":"%s","icon":"download"}::', $btnLabel, $url);
        $markdownLink = sprintf('[%s](%s)', $btnLabel, $url);

        return [
            'ok' => true,
            'report_type' => $type,
            'report_name' => $reportName,
            'short_name' => $shortName,
            'format' => $format,
            'period' => $periodLabel,
            'download_url' => $url,
            'action_button' => $actionButton,
            'markdown_link' => $markdownLink,
            'instructions' => 'Sertakan tombol action_button ini dalam respon Anda agar pengguna bisa langsung mendownload file laporan dengan satu klik.',
        ];
    }
}
