<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\JournalEntryOptionResolver;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Assets\Services\AssetService;
use App\Http\Requests\Accounting\JournalEntryRequest;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class JournalEntryController
{
    public function __construct(
        private readonly JournalEntryOptionResolver $resolver,
    ) {}

    public function create(Request $request, TenantContext $context): Response
    {
        $history = $this->resolveHistory($request, $context);
        $types = $this->resolver->getTransactionTypes();
        $allowed = array_column($types, 'value');
        $preset = (string) $request->query('type', '');
        if ($preset !== '' && ! in_array($preset, $allowed, true)) {
            $preset = '';
        }

        return Inertia::render('Accounting/JournalEntries/Create', [
            'transactionTypes' => $types,
            'labels' => $this->resolver->getLabels(),
            'options' => $this->resolver->getOptionsForAllTypes(),
            'accountOptions' => $this->resolver->getAllAccountOptions(),
            'today' => now()->toDateString(),
            'history' => $history,
            'presetType' => $preset !== '' ? $preset : null,
        ]);
    }

    public function store(JournalEntryRequest $request, JournalPostingService $poster): RedirectResponse
    {
        $data = $request->validated();
        $userId = (int) $request->user()->row_id;
        $isInventory = JournalEntryOptionResolver::isAssetPurchase($data['transaction_type'] ?? null);

        if ($isInventory) {
            $qty = (int) ($data['asset_quantity'] ?? 0);
            $unit = (float) ($data['asset_unit_cost'] ?? 0);
            $data['amount'] = round($qty * $unit, 2);
            $name = trim((string) ($data['asset_name'] ?? ''));
            $data['description'] = trim((string) ($data['description'] ?? '')) !== ''
                ? (string) $data['description']
                : sprintf('Pembelian inventaris: %s (%d unit)', $name, $qty);
        }

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
                'account_row_id' => (int) $data['disimpan_ke_row_id'],
                'organization_unit_row_id' => null,
                'description' => $data['description'],
                'debit' => (float) $data['amount'],
                'credit' => 0,
            ]);

            $entry->lines()->create([
                'line_number' => 2,
                'account_row_id' => (int) $data['sumber_dana_row_id'],
                'organization_unit_row_id' => null,
                'description' => $data['description'],
                'debit' => 0,
                'credit' => (float) $data['amount'],
            ]);

            if ($isInventory) {
                // Register inventaris ikut jurnal (sama alur legacy) — daftar di /assets.
                $asset = app(AssetService::class)->create([
                    'name' => (string) $data['asset_name'],
                    'purchased_at' => $data['transaction_date'],
                    'quantity' => (int) $data['asset_quantity'],
                    'unit_cost' => (float) $data['asset_unit_cost'],
                    'useful_life_months' => (int) $data['asset_useful_life_months'],
                    'status' => 'good',
                    'category_code' => JournalEntryOptionResolver::ATB_PURCHASE_TYPES[$data['transaction_type']] ?? null,
                ], $userId);

                $entry->update(['source_row_id' => (int) $asset->row_id]);
            }

            return $entry->fresh(['lines']);
        });

        $posted = $poster->post($entry, $userId);

        session()->flash('success', [
            'message' => 'Jurnal umum berhasil dicatat.',
            'entry' => [
                'id' => $posted->id,
                'public_id' => $posted->public_id,
                'journal_number' => $posted->journal_number,
                'transaction_date' => $posted->transaction_date?->toDateString(),
                'transaction_type' => $posted->transaction_type,
                'description' => $posted->description,
            ],
            'lines' => $posted->lines->map(fn ($l) => [
                'account_code' => $l->account?->code,
                'account_name' => $l->account?->name,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
            ])->all(),
        ]);

        return redirect()->route('accounting.journal-entries.create');
    }

    /**
     * @return array{
     *     account: array{row_id:int,code:string,name:string,normal_balance:string,account_type:string}|null,
     *     period: string,
     *     date: string|null,
     *     month: string|null,
     *     year: string|null,
     *     range: array{start:string,end:string},
     *     rows: array<int, array<string, mixed>>
     * }|null
     */
    private function resolveHistory(Request $request, TenantContext $context): ?array
    {
        $accountRowId = $request->query('account_row_id');
        if (! is_string($accountRowId) || $accountRowId === '') {
            return null;
        }

        $validated = $request->validate([
            'account_row_id' => ['required', 'integer'],
            'period' => ['nullable', Rule::in(['daily', 'monthly', 'yearly'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'date_format:Y'],
        ]);

        $period = $validated['period'] ?? 'monthly';
        $tenantId = $context->id();

        $account = Account::on('tenant')
            ->where('row_id', (int) $validated['account_row_id'])
            ->where('is_active', true)
            ->first(['row_id', 'code', 'name', 'normal_balance', 'account_type']);

        if ($account === null) {
            return null;
        }

        $today = CarbonImmutable::today();

        $range = match ($period) {
            'daily' => $this->dailyRange($validated['date'] ?? $today->toDateString()),
            'monthly' => $this->monthlyRange($validated['month'] ?? $today->format('Y-m')),
            'yearly' => $this->yearlyRange($validated['year'] ?? (string) $today->year),
        };

        $rows = DB::connection('tenant')
            ->table('journal_lines as l')
            ->join('journal_entries as e', function ($join): void {
                $join->on('e.tenant_id', '=', 'l.tenant_id')
                    ->on('e.row_id', '=', 'l.journal_entry_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('l.account_row_id', $account->row_id)
            ->where('e.status', 'posted')
            ->whereBetween('e.transaction_date', [$range['start'], $range['end']])
            ->orderBy('e.transaction_date')
            ->orderBy('e.sequence_number')
            ->orderBy('l.line_number')
            ->get([
                'e.id as entry_id',
                'e.transaction_date',
                'e.sequence_number',
                'e.journal_number',
                'e.public_id as entry_public_id',
                'e.description as entry_description',
                'e.transaction_type',
                'l.debit',
                'l.credit',
                'l.description as line_description',
            ]);

        $normal = (string) $account->normal_balance;
        $running = 0.0;
        $rendered = [];
        $rowCounter = 0;
        foreach ($rows as $row) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $running += $normal === 'D' ? ($debit - $credit) : ($credit - $debit);
            $rowCounter++;
            $rendered[] = [
                'entry_id' => (int) $row->entry_id,
                'transaction_date' => (string) $row->transaction_date,
                'row_sequence' => $rowCounter,
                'sequence_number' => (int) $row->sequence_number,
                'journal_number' => $row->journal_number,
                'entry_public_id' => $row->entry_public_id,
                'transaction_type' => $row->transaction_type,
                'description' => $row->line_description ?: $row->entry_description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $running,
            ];
        }

        return [
            'account' => [
                'row_id' => (int) $account->row_id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'normal_balance' => $normal,
                'account_type' => (string) $account->account_type,
            ],
            'period' => $period,
            'date' => $validated['date'] ?? null,
            'month' => $validated['month'] ?? null,
            'year' => $validated['year'] ?? null,
            'range' => $range,
            'rows' => $rendered,
        ];
    }

    /**
     * @return array{start:string,end:string}
     */
    private function dailyRange(string $date): array
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date) ?? CarbonImmutable::today();

        return [
            'start' => $day->toDateString(),
            'end' => $day->toDateString(),
        ];
    }

    /**
     * @return array{start:string,end:string}
     */
    private function monthlyRange(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m', $month) ?? CarbonImmutable::now()->startOfMonth();

        return [
            'start' => $start->startOfMonth()->toDateString(),
            'end' => $start->endOfMonth()->toDateString(),
        ];
    }

    /**
     * @return array{start:string,end:string}
     */
    private function yearlyRange(string $year): array
    {
        $start = CarbonImmutable::createFromFormat('Y', $year) ?? CarbonImmutable::now()->startOfYear();

        return [
            'start' => $start->startOfYear()->toDateString(),
            'end' => $start->endOfYear()->toDateString(),
        ];
    }
}
