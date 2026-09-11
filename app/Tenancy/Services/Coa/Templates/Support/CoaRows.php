<?php

declare(strict_types=1);

namespace App\Tenancy\Services\Coa\Templates\Support;

/**
 * Shared row groups for parity-focused trading and cooperative COA variants.
 */
final class CoaRows
{
    public const ASSET_LIABILITY_AND_EQUITY = [
        ['code' => '1.0.00.00', 'name' => 'Aset', 'normal' => 'D', 'level' => 1, 'parent_code' => null],
        ['code' => '2.0.00.00', 'name' => 'Utang', 'normal' => 'C', 'level' => 1, 'parent_code' => null],
        ['code' => '3.0.00.00', 'name' => 'Modal', 'normal' => 'C', 'level' => 1, 'parent_code' => null],
        ['code' => '4.0.00.00', 'name' => 'Pendapatan', 'normal' => 'C', 'level' => 1, 'parent_code' => null],
        ['code' => '5.0.00.00', 'name' => 'Beban', 'normal' => 'D', 'level' => 1, 'parent_code' => null],
        ['code' => '7.0.00.00', 'name' => 'Pajak dan Pendapatan Lain-lain', 'normal' => 'D', 'level' => 1, 'parent_code' => null],
        ['code' => '1.1.00.00', 'name' => 'Aset Lancar', 'normal' => 'D', 'level' => 2, 'parent_code' => '1.0.00.00'],
        ['code' => '1.2.00.00', 'name' => 'Aset Tidak Lancar', 'normal' => 'D', 'level' => 2, 'parent_code' => '1.0.00.00'],
        ['code' => '2.1.00.00', 'name' => 'Utang Jangka Pendek', 'normal' => 'C', 'level' => 2, 'parent_code' => '2.0.00.00'],
        ['code' => '2.2.00.00', 'name' => 'Utang Jangka Panjang', 'normal' => 'C', 'level' => 2, 'parent_code' => '2.0.00.00'],
        ['code' => '3.1.00.00', 'name' => 'Simpanan dan Modal', 'normal' => 'C', 'level' => 2, 'parent_code' => '3.0.00.00'],
        ['code' => '3.3.00.00', 'name' => 'Modal Koperasi', 'normal' => 'C', 'level' => 2, 'parent_code' => '3.0.00.00'],
        ['code' => '1.1.01.00', 'name' => 'Kas', 'normal' => 'D', 'level' => 3, 'parent_code' => '1.1.00.00'],
        ['code' => '1.1.02.00', 'name' => 'Kas Setara Kas', 'normal' => 'D', 'level' => 3, 'parent_code' => '1.1.00.00'],
        ['code' => '1.2.01.00', 'name' => 'Aktiva Tetap dan Inventaris', 'normal' => 'D', 'level' => 3, 'parent_code' => '1.2.00.00'],
        ['code' => '1.2.02.00', 'name' => 'Akumulasi Penyusutan', 'normal' => 'D', 'level' => 3, 'parent_code' => '1.2.00.00'],
        ['code' => '3.1.01.00', 'name' => 'Simpanan Pokok', 'normal' => 'C', 'level' => 3, 'parent_code' => '3.1.00.00'],
        ['code' => '3.1.02.00', 'name' => 'Simpanan Wajib', 'normal' => 'C', 'level' => 3, 'parent_code' => '3.1.00.00'],
        ['code' => '3.1.03.00', 'name' => 'Simpanan Sukarela', 'normal' => 'C', 'level' => 3, 'parent_code' => '3.1.00.00'],
        ['code' => '3.3.03.00', 'name' => 'SHU Tahun Berjalan', 'normal' => 'C', 'level' => 3, 'parent_code' => '3.3.00.00'],
        ['code' => '1.1.01.01', 'name' => 'Kas', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.01.00'],
        ['code' => '1.1.02.01', 'name' => 'Bank', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.02.00'],
        ['code' => '3.1.01.01', 'name' => 'Simpanan Pokok Anggota', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.1.01.00'],
        ['code' => '3.1.02.01', 'name' => 'Simpanan Wajib Anggota', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.1.02.00'],
        ['code' => '3.1.03.01', 'name' => 'Simpanan Sukarela Anggota', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.1.03.00'],
        ['code' => '3.3.03.01', 'name' => 'SHU Tahun Berjalan Koperasi', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.3.03.00'],
    ];

    public const TRADING_INCOME = [
        ['code' => '4.1.00.00', 'name' => 'Pendapatan Usaha', 'normal' => 'C', 'level' => 2, 'parent_code' => '4.0.00.00'],
        ['code' => '4.2.00.00', 'name' => 'Pendapatan Non Usaha', 'normal' => 'C', 'level' => 2, 'parent_code' => '4.0.00.00'],
        ['code' => '4.1.01.00', 'name' => 'Penjualan', 'normal' => 'C', 'level' => 3, 'parent_code' => '4.1.00.00'],
        ['code' => '4.2.01.00', 'name' => 'Pendapatan Non Usaha', 'normal' => 'C', 'level' => 3, 'parent_code' => '4.2.00.00'],
        ['code' => '4.1.01.01', 'name' => 'Penjualan', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00'],
        ['code' => '4.1.01.02', 'name' => 'Diskon Penjualan', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00'],
        ['code' => '4.1.01.03', 'name' => 'Retur Penjualan', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00'],
        ['code' => '4.1.01.06', 'name' => 'Cashback Penjualan', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00'],
        ['code' => '4.2.01.01', 'name' => 'Pendapatan Non Usaha Lainnya', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.2.01.00'],
    ];

    public const TRADING_INVENTORY = [
        ['code' => '1.1.03.00', 'name' => 'Persediaan', 'normal' => 'D', 'level' => 3, 'parent_code' => '1.1.00.00'],
        ['code' => '1.1.03.01', 'name' => 'Persediaan Barang', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00'],
        ['code' => '1.1.03.02', 'name' => 'Pembelian Persediaan', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00'],
        ['code' => '1.1.03.03', 'name' => 'Diskon Pembelian', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00'],
        ['code' => '1.1.03.04', 'name' => 'Retur Pembelian', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00'],
        ['code' => '1.1.03.06', 'name' => 'Cashback Pembelian', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00'],
    ];

    public static function tradingCostOfGoodsSold(): array
    {
        return [
            ...self::TRADING_INVENTORY,
            ['code' => '5.1.00.00', 'name' => 'Beban dan Harga Pokok', 'normal' => 'D', 'level' => 2, 'parent_code' => '5.0.00.00'],
            ['code' => '5.1.01.00', 'name' => 'Harga Pokok dan Pembelian', 'normal' => 'D', 'level' => 3, 'parent_code' => '5.1.00.00'],
            ['code' => '5.1.01.01', 'name' => 'Harga Pokok Penjualan', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00'],
            ['code' => '5.1.01.02', 'name' => 'Diskon Pembelian', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00'],
            ['code' => '5.1.01.03', 'name' => 'Retur Pembelian', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00'],
            ['code' => '5.1.01.06', 'name' => 'Cashback Pembelian', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00'],
        ];
    }

    public static function sharedExpenses(): array
    {
        return [
            ['code' => '5.1.02.00', 'name' => 'Beban Gaji dan Honor', 'normal' => 'D', 'level' => 3, 'parent_code' => '5.1.00.00'],
            ['code' => '5.1.03.00', 'name' => 'Beban Operasional', 'normal' => 'D', 'level' => 3, 'parent_code' => '5.1.00.00'],
            ['code' => '5.1.04.00', 'name' => 'Beban Non Usaha', 'normal' => 'D', 'level' => 3, 'parent_code' => '5.1.00.00'],
            ['code' => '5.1.02.01', 'name' => 'Beban Gaji', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.02.00'],
            ['code' => '5.1.02.02', 'name' => 'Beban Honor', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.02.00'],
            ['code' => '5.1.03.01', 'name' => 'Beban Listrik dan Air', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.03.00'],
            ['code' => '5.1.03.02', 'name' => 'Beban Transportasi', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.03.00'],
            ['code' => '5.1.04.01', 'name' => 'Beban Administrasi Bank', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.04.00'],
        ];
    }

    public static function tradingTax(): array
    {
        return [
            ['code' => '7.1.00.00', 'name' => 'Pendapatan Lain-lain', 'normal' => 'C', 'level' => 2, 'parent_code' => '7.0.00.00'],
            ['code' => '7.4.00.00', 'name' => 'Pajak', 'normal' => 'D', 'level' => 2, 'parent_code' => '7.0.00.00'],
            ['code' => '7.1.01.01', 'name' => 'Pendapatan Lain-lain', 'normal' => 'C', 'level' => 4, 'parent_code' => '7.1.00.00'],
            ['code' => '7.4.01.00', 'name' => 'PPh Terutang', 'normal' => 'D', 'level' => 3, 'parent_code' => '7.4.00.00'],
            ['code' => '7.4.02.00', 'name' => 'Pajak Lainnya', 'normal' => 'D', 'level' => 3, 'parent_code' => '7.4.00.00'],
            ['code' => '7.4.01.01', 'name' => 'PPh Terutang', 'normal' => 'D', 'level' => 4, 'parent_code' => '7.4.01.00'],
            ['code' => '7.4.02.01', 'name' => 'Pajak Lainnya', 'normal' => 'D', 'level' => 4, 'parent_code' => '7.4.02.00'],
        ];
    }

    public static function cooperativeIncome(): array
    {
        return [
            ['code' => '4.1.00.00', 'name' => 'Pendapatan Usaha', 'normal' => 'C', 'level' => 2, 'parent_code' => '4.0.00.00'],
            ['code' => '4.2.00.00', 'name' => 'Pendapatan Non Usaha', 'normal' => 'C', 'level' => 2, 'parent_code' => '4.0.00.00'],
            ['code' => '4.1.01.00', 'name' => 'Hasil Usaha Unit', 'normal' => 'C', 'level' => 3, 'parent_code' => '4.1.00.00'],
            ['code' => '4.2.01.00', 'name' => 'Pendapatan Non Usaha', 'normal' => 'C', 'level' => 3, 'parent_code' => '4.2.00.00'],
            ['code' => '4.1.01.01', 'name' => 'Hasil Usaha Unit Simpan Pinjam', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00'],
            ['code' => '4.1.01.02', 'name' => 'Hasil Usaha Unit Dagang', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00'],
            ['code' => '4.1.01.03', 'name' => 'Hasil Usaha Unit Jasa', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00'],
            ['code' => '4.2.01.01', 'name' => 'Pendapatan Non Usaha Lainnya', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.2.01.00'],
        ];
    }

    public static function cooperativeExpenses(): array
    {
        return [
            ['code' => '5.1.00.00', 'name' => 'Beban Usaha', 'normal' => 'D', 'level' => 2, 'parent_code' => '5.0.00.00'],
            ['code' => '5.2.00.00', 'name' => 'Beban Pemasaran', 'normal' => 'D', 'level' => 2, 'parent_code' => '5.0.00.00'],
            ['code' => '5.3.00.00', 'name' => 'Beban Non Usaha', 'normal' => 'D', 'level' => 2, 'parent_code' => '5.0.00.00'],
            ['code' => '5.1.01.00', 'name' => 'Beban Gaji dan Honor', 'normal' => 'D', 'level' => 3, 'parent_code' => '5.1.00.00'],
            ['code' => '5.1.02.00', 'name' => 'Beban Operasional', 'normal' => 'D', 'level' => 3, 'parent_code' => '5.1.00.00'],
            ['code' => '5.1.01.01', 'name' => 'Beban Gaji', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00'],
            ['code' => '5.1.01.02', 'name' => 'Beban Honor', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00'],
            ['code' => '5.1.02.01', 'name' => 'Beban Operasional Unit', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.02.00'],
            ['code' => '5.2.01.01', 'name' => 'Beban Pemasaran', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.2.00.00'],
            ['code' => '5.3.01.01', 'name' => 'Beban Kegiatan Sosial', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.3.00.00'],
        ];
    }

    public static function cooperativeTax(): array
    {
        return [
            ['code' => '5.4.00.00', 'name' => 'Beban Pajak', 'normal' => 'D', 'level' => 2, 'parent_code' => '5.0.00.00'],
            ['code' => '5.4.01.00', 'name' => 'Beban Pajak', 'normal' => 'D', 'level' => 3, 'parent_code' => '5.4.00.00'],
            ['code' => '5.4.01.01', 'name' => 'Taksiran PPh', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.4.01.00'],
        ];
    }
}
