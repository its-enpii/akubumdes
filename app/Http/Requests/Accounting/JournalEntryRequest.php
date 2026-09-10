<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\JournalEntryOptionResolver;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class JournalEntryRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $accountExists = Rule::exists(Account::class, 'row_id')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)->where('is_postable', true));

        $type = (string) $this->input('transaction_type', '');
        $isAssetPurchase = JournalEntryOptionResolver::isAssetPurchase($type);
        $isLandPurchase = $type === 'pembelian_aset_tanah';
        $allowedTypes = array_merge(array_keys(JournalEntryOptionResolver::TYPES), ['pembelian_inventaris']);

        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_type' => ['required', Rule::in($allowedTypes)],
            'description' => [$isAssetPurchase ? 'nullable' : 'required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'sumber_dana_row_id' => ['required', 'integer', 'different:disimpan_ke_row_id', $accountExists],
            'disimpan_ke_row_id' => ['required', 'integer', 'different:sumber_dana_row_id', $accountExists],
            'asset_name' => [$isAssetPurchase ? 'required' : 'nullable', 'string', 'max:180'],
            'asset_quantity' => [$isAssetPurchase ? 'required' : 'nullable', 'integer', 'min:1', 'max:999999'],
            'asset_unit_cost' => [$isAssetPurchase ? 'required' : 'nullable', 'numeric', 'min:1'],
            // Tanah: 0 / null = tidak disusutkan; lainnya min 1.
            'asset_useful_life_months' => [
                $isAssetPurchase ? 'required' : 'nullable',
                'integer',
                $isLandPurchase ? 'min:0' : 'min:1',
                'max:1200',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! JournalEntryOptionResolver::isAssetPurchase((string) $this->input('transaction_type', ''))) {
                return;
            }

            $qty = (int) $this->input('asset_quantity', 0);
            $unitCost = (float) $this->input('asset_unit_cost', 0);
            $expected = round($qty * $unitCost, 2);
            $amount = round((float) $this->input('amount', 0), 2);

            if ($qty > 0 && $unitCost > 0 && $amount !== $expected) {
                $validator->errors()->add(
                    'amount',
                    'Harga perolehan harus sama dengan jml unit × harga satuan ('.number_format($expected, 0, ',', '.').').',
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'transaction_date' => 'tanggal transaksi',
            'transaction_type' => 'jenis transaksi',
            'description' => 'keterangan',
            'reference' => 'relasi',
            'amount' => 'harga perolehan',
            'sumber_dana_row_id' => 'sumber dana',
            'disimpan_ke_row_id' => 'disimpan ke',
            'asset_name' => 'nama barang',
            'asset_quantity' => 'jumlah unit',
            'asset_unit_cost' => 'harga satuan',
            'asset_useful_life_months' => 'umur ekonomis',
        ];
    }

    public function messages(): array
    {
        return [
            'sumber_dana_row_id.different' => 'Akun sumber dana dan disimpan ke tidak boleh sama.',
            'disimpan_ke_row_id.different' => 'Akun sumber dana dan disimpan ke tidak boleh sama.',
        ];
    }
}
