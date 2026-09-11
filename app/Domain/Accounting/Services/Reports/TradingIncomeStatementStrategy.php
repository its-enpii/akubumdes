<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

final class TradingIncomeStatementStrategy extends BaseIncomeStatementStrategy
{
    public function title(): string
    {
        return 'Laporan Laba Rugi';
    }

    protected function sections(array $rows): array
    {
        $rows = array_column($rows, null, 'code');
        $penjualan = $this->value($rows, '4.1.01.01', 'ytd');
        $diskonPenjualan = $this->value($rows, '4.1.01.02', 'ytd');
        $returPenjualan = $this->value($rows, '4.1.01.03', 'ytd');
        $cashbackPenjualan = $this->value($rows, '4.1.01.06', 'ytd');
        $penjualanBersih = round($penjualan + $diskonPenjualan + $returPenjualan + $cashbackPenjualan, 2);

        $persediaanAkhir = $this->value($rows, '1.1.03.01', 'ytd');
        $pembelian = $this->value($rows, '1.1.03.02', 'ytd');
        $diskonPembelian = $this->value($rows, '5.1.01.02', 'ytd');
        $returPembelian = $this->value($rows, '5.1.01.03', 'ytd');
        $cashbackPembelian = $this->value($rows, '5.1.01.06', 'ytd');
        $pembelianBersih = round($pembelian + $diskonPembelian + $returPembelian + $cashbackPembelian, 2);
        $persediaanAwal = round($this->value($rows, '1.1.03.01', 'prior') - $persediaanAkhir + $pembelianBersih, 2);
        $totalPersediaan = round($persediaanAwal + $pembelianBersih, 2);

        $hppYtd = $this->value($rows, '5.1.01.01', 'ytd');
        if ($hppYtd === 0.0) {
            $hppYtd = round($totalPersediaan - $persediaanAkhir, 2);
        }

        $bebanLain = array_values(array_filter($rows, function (array $row): bool {
            $code = (string) $row['code'];

            return str_starts_with($code, '5.') && ! in_array($code, ['5.1.01.01', '5.1.01.02', '5.1.01.03', '5.1.01.06', '5.4.01.01'], true);
        }));

        $pajak = array_values(array_filter($rows, fn (array $row): bool => str_starts_with((string) $row['code'], '7.4')));

        $pajakTotal = $this->total($pajak, 'ytd');
        $labaKotor = round($penjualanBersih - $hppYtd, 2);
        $labaSebelumPajak = round($labaKotor - $this->total($bebanLain, 'ytd'), 2);

        return [
            [
                'label' => 'Penjualan Bersih',
                'type' => 'revenue',
                'rows' => [
                    ['code' => '4.1.01.01', 'name' => 'Penjualan', ...$this->triple($rows, '4.1.01.01')],
                    ['code' => '4.1.01.02', 'name' => 'Diskon Penjualan', ...$this->triple($rows, '4.1.01.02')],
                    ['code' => '4.1.01.03', 'name' => 'Retur Penjualan', ...$this->triple($rows, '4.1.01.03')],
                    ['code' => '4.1.01.06', 'name' => 'Cashback Penjualan', ...$this->triple($rows, '4.1.01.06')],
                ],
                'total' => $penjualanBersih,
                'current' => 0.0,
                'prior' => 0.0,
            ],
            [
                'label' => 'Harga Pokok Penjualan',
                'type' => 'expense',
                'rows' => [
                    ['code' => '', 'name' => 'Persediaan Awal', ...$this->prior($rows, '1.1.03.01')],
                    ['code' => '1.1.03.02', 'name' => 'Pembelian', ...$this->triple($rows, '1.1.03.02')],
                    ['code' => '5.1.01.02', 'name' => 'Diskon Pembelian', ...$this->triple($rows, '5.1.01.02')],
                    ['code' => '5.1.01.03', 'name' => 'Retur Pembelian', ...$this->triple($rows, '5.1.01.03')],
                    ['code' => '5.1.01.06', 'name' => 'Cashback Pembelian', ...$this->triple($rows, '5.1.01.06')],
                    ['code' => '', 'name' => 'Total Persediaan', ...$this->valueTriple($totalPersediaan)],
                    ['code' => '', 'name' => 'Persediaan Akhir', ...$this->valueTriple($persediaanAkhir)],
                    ['code' => '', 'name' => 'Harga Pokok Penjualan', ...$this->valueTriple($hppYtd)],
                ],
                'total' => $hppYtd,
                'current' => 0.0,
                'prior' => 0.0,
            ],
            [
                'label' => 'Beban Lainnya',
                'type' => 'expense',
                'rows' => $bebanLain,
                'total' => $this->total($bebanLain, 'ytd'),
                'current' => 0.0,
                'prior' => 0.0,
            ],
            [
                'label' => 'Pajak',
                'type' => 'expense',
                'rows' => $pajak,
                'total' => $pajakTotal,
                'current' => 0.0,
                'prior' => 0.0,
            ],
        ];
    }

    protected function summary(array $rows): array
    {
        $rows = array_column($rows, null, 'code');
        $penjualanBersih = round(
            $this->value($rows, '4.1.01.01', 'ytd')
                + $this->value($rows, '4.1.01.02', 'ytd')
                + $this->value($rows, '4.1.01.03', 'ytd')
                + $this->value($rows, '4.1.01.06', 'ytd'),
            2,
        );

        $pembelianBersih = round(
            $this->value($rows, '1.1.03.02', 'ytd')
                + $this->value($rows, '5.1.01.02', 'ytd')
                + $this->value($rows, '5.1.01.03', 'ytd')
                + $this->value($rows, '5.1.01.06', 'ytd'),
            2,
        );

        $persediaanAwal = $this->value($rows, '1.1.03.01', 'prior') - $this->value($rows, '1.1.03.01', 'ytd') + $pembelianBersih;
        $persediaanAkhir = $this->value($rows, '1.1.03.01', 'ytd');
        $hpp = $this->value($rows, '5.1.01.01', 'ytd');
        if ($hpp === 0.0) {
            $hpp = round($persediaanAwal + $pembelianBersih - $persediaanAkhir, 2);
        }

        $bebanLain = 0.0;
        $pajak = 0.0;
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            if (str_starts_with($code, '5.') && ! in_array($code, ['5.1.01.01', '5.1.01.02', '5.1.01.03', '5.1.01.06', '5.4.01.01'], true)) {
                $bebanLain = round($bebanLain + (float) $row['ytd'], 2);
            }

            if (str_starts_with($code, '7.4')) {
                $pajak = round($pajak + (float) $row['ytd'], 2);
            }
        }

        $labaKotor = round($penjualanBersih - $hpp, 2);
        $labaSebelumPajak = round($labaKotor - $bebanLain, 2);
        $labaBersih = round($labaSebelumPajak - $pajak, 2);

        return [
            'operating' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => $labaKotor],
            'non_operating' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => round(-$bebanLain, 2)],
            'before_tax' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => $labaSebelumPajak],
            'tax' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => $pajak],
            'after_tax' => ['prior' => 0.0, 'current' => 0.0, 'ytd' => $labaBersih],
        ];
    }

    private function triple(array $rows, string $code): array
    {
        return [
            'prior' => $this->value($rows, $code, 'prior'),
            'current' => $this->value($rows, $code, 'current'),
            'ytd' => $this->value($rows, $code, 'ytd'),
        ];
    }

    private function prior(array $rows, string $code): array
    {
        return [
            'prior' => $this->value($rows, $code, 'prior'),
            'current' => $this->value($rows, $code, 'ytd'),
            'ytd' => $this->value($rows, $code, 'ytd'),
        ];
    }

    private function valueTriple(float $value): array
    {
        return ['prior' => 0.0, 'current' => 0.0, 'ytd' => $value];
    }
}
