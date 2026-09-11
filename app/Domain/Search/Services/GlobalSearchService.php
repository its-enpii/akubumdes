<?php

declare(strict_types=1);

namespace App\Domain\Search\Services;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Assets\Models\Asset;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Header omnibox — jump to entity, not a full-text engine.
 */
final class GlobalSearchService
{
    private const LIMIT_PER = 5;

    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionChecker $permissions,
    ) {}

    /**
     * @return array{
     *   q: string,
     *   groups: list<array{key:string,label:string,items:list<array{title:string,subtitle:?string,href:string,icon:string}>}>
     * }
     */
    public function search(string $q, User $user): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return ['q' => $q, 'groups' => []];
        }

        $groups = [];

        if ($this->permissions->allows($user, 'journals.view')) {
            $items = $this->journals($q);
            if ($items !== []) {
                $groups[] = ['key' => 'journals', 'label' => 'Jurnal', 'items' => $items];
            }
            $items = $this->assets($q);
            if ($items !== []) {
                $groups[] = ['key' => 'assets', 'label' => 'Inventaris', 'items' => $items];
            }
        }

        return ['q' => $q, 'groups' => $groups];
    }

    /**
     * @return list<array{title:string,subtitle:?string,href:string,icon:string}>
     */
    private function journals(string $q): array
    {
        $term = '%'.$q.'%';
        $query = JournalEntry::query()
            ->where('status', 'posted')
            ->where(function ($w) use ($term, $q): void {
                $w->where('journal_number', 'like', $term)
                    ->orWhere('description', 'like', $term);
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q)->orWhere('row_id', (int) $q);
                }
            })
            ->orderByDesc('transaction_date')
            ->orderByDesc('row_id')
            ->limit(self::LIMIT_PER)
            ->get();

        return $query->map(fn (JournalEntry $e): array => [
            'title' => (string) ($e->journal_number ?: 'Jurnal #'.$e->id),
            'subtitle' => trim(implode(' · ', array_filter([
                $e->transaction_date?->format('Y-m-d'),
                $e->source_type,
                $e->description ? mb_substr((string) $e->description, 0, 60) : null,
            ]))) ?: null,
            // Daftar jurnal filter by q (no dedicated show yet).
            'href' => '/accounting/journals?q='.urlencode((string) ($e->journal_number ?: $e->id)),
            'icon' => 'receipt_long',
        ])->all();
    }

    /**
     * @return list<array{title:string,subtitle:?string,href:string,icon:string}>
     */
    private function assets(string $q): array
    {
        $term = '%'.$q.'%';

        return Asset::query()
            ->where(function ($w) use ($term): void {
                $w->where('name', 'like', $term)->orWhere('asset_code', 'like', $term);
            })
            ->orderBy('name')
            ->limit(self::LIMIT_PER)
            ->get()
            ->map(fn (Asset $a): array => [
                'title' => (string) $a->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $a->asset_code,
                    $a->status,
                ]))) ?: null,
                'href' => '/accounting/assets/'.$a->row_id,
                'icon' => 'inventory_2',
            ])
            ->all();
    }
}
