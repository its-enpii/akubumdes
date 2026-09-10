<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Services;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Support\Csv;
use App\Tenancy\Services\TenantSequenceService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TenantOnboardingService
{
    /**
     * Post journal saldo awal / opening balances.
     *
     * @param  array<int, array{account_row_id: int, debit: float, credit: float}>  $lines
     */
    public function saveOpeningBalances(array $lines, string $asOfDate, int $userId): JournalEntry
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $validLines = [];

        foreach ($lines as $line) {
            $accountRowId = (int) ($line['account_row_id'] ?? 0);
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($accountRowId <= 0 || ($debit <= 0 && $credit <= 0)) {
                continue;
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
            $validLines[] = [
                'account_row_id' => $accountRowId,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (empty($validLines)) {
            throw new InvalidArgumentException('Minimal 1 baris saldo awal akun harus diisi.');
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new InvalidArgumentException(sprintf(
                'Saldo awal tidak imbang (Unbalanced)! Total Debit: Rp %s vs Total Kredit: Rp %s (Selisih: Rp %s).',
                number_format($totalDebit, 2, ',', '.'),
                number_format($totalCredit, 2, ',', '.'),
                number_format(abs($totalDebit - $totalCredit), 2, ',', '.'),
            ));
        }

        return DB::connection('tenant')->transaction(function () use ($validLines, $asOfDate, $userId): JournalEntry {
            $prefix = date('ym', strtotime($asOfDate));
            $seq = app(TenantSequenceService::class)->next('journal_number:'.$prefix);
            $journalNumber = $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

            $entry = JournalEntry::query()->create([
                'journal_number' => $journalNumber,
                'transaction_date' => $asOfDate,
                'transaction_type' => 'pemindahan_saldo',
                'description' => 'Posting Saldo Awal Keuangan Tenant Baru (Opening Balances - '.$asOfDate.')',
                'status' => 'posted',
                'posted_at' => now(),
                'created_by_user_id' => $userId,
            ]);

            $lineNumber = 1;
            foreach ($validLines as $l) {
                JournalLine::query()->create([
                    'journal_entry_row_id' => $entry->row_id,
                    'line_number' => $lineNumber++,
                    'account_row_id' => $l['account_row_id'],
                    'debit' => $l['debit'],
                    'credit' => $l['credit'],
                    'description' => 'Saldo Awal',
                ]);
            }

            return $entry;
        });
    }

    /**
     * Download CSV template file for onboarding data.
     */
    public function downloadCsvTemplate(string $type): StreamedResponse
    {
        // Canonicalize English variants to the Indonesian type names so that
        // /onboarding/templates/{type} accepts both `members` and `anggota`,
        // `groups` / `kelompok`, dst.
        $type = match ($type) {
            'members', 'member' => 'anggota',
            'groups', 'group', 'kelompoks' => 'kelompok',
            'opening-balances', 'opening-balance' => 'saldo-awal',
            'assets', 'asset', 'fixed-assets' => 'aset-tetap',
            default => $type,
        };

        return match ($type) {
            'saldo-awal' => Csv::download('template_saldo_awal.csv', [
                'kode_akun', 'nama_akun', 'debit', 'kredit',
            ], [
                ['1.1.01.01', 'Kas Kantor', '10000000', '0'],
                ['1.1.02.01', 'Bank BRI', '25000000', '0'],
                ['3.1.01.01', 'Modal Diterima', '0', '35000000'],
            ]),

            'anggota' => Csv::download('template_anggota.csv', [
                'nik', 'nama', 'jenis_kelamin', 'alamat', 'desa', 'no_hp', 'status',
            ], [
                ['3515011203900001', 'Siti Aminah', 'P', 'Jl. Mawar No. 12', 'Desa Maju', '081234567890', 'active'],
                ['3515011203900002', 'Budi Santoso', 'L', 'RT 02 RW 01', 'Desa Maju', '081987654321', 'active'],
            ]),

            'kelompok' => Csv::download('template_kelompok.csv', [
                'nama', 'desa', 'alamat', 'no_hp',
            ], [
                ['Kelompok Melati 01', 'Desa Maju', 'RT 01 RW 01', '081234567800'],
                ['Kelompok Seroja 02', 'Desa Makmur', 'RT 03 RW 02', '081234567801'],
            ]),

            'aset-tetap' => Csv::download('template_aset_tetap.csv', [
                'nama_barang', 'tanggal_perolehan', 'harga_perolehan', 'akumulasi_penyusutan_awal', 'umur_ekonomis_bulan',
            ], [
                ['Laptop HP ProBook', '2024-01-10', '8500000', '1700000', '48'],
                ['Sepeda Motor Honda Beat', '2023-05-20', '18000000', '7200000', '60'],
            ]),

            default => throw new InvalidArgumentException("Tipe template '{$type}' tidak dikenal."),
        };
    }
}
