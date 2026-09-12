<?php

declare(strict_types=1);

namespace App\Domain\Migration\Accounting;

use App\Domain\Migration\Support\LegacyConnection;
use Generator;

final class LegacyAccountingExtractor
{
    public function __construct(
        private LegacyConnection $legacy,
    ) {}

    /**
     * @return Generator<int, object>
     */
    public function transaksiChunks(
        string $suffix,
        int $chunk,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): Generator {
        $table = $this->legacy->transaksiTable($suffix);
        $this->legacy->assertSafeTableName($table);
        if (! $this->legacy->tableExists($table)) {
            throw new \RuntimeException("Table [{$table}] does not exist on legacy DB.");
        }

        $cols = array_map(static fn (object $c): string => (string) ($c->COLUMN_NAME ?? ''), $this->legacy->columns($table));
        $hasDeletedAt = in_array('deleted_at', $cols, true);
        $hasUrutan = in_array('urutan', $cols, true);
        $hasIdtp = in_array('idtp', $cols, true);
        $hasIdPinj = in_array('id_pinj', $cols, true);
        $hasIdPinjI = in_array('id_pinj_i', $cols, true);
        $hasKeterangan = in_array('keterangan_transaksi', $cols, true);
        $hasRelasi = in_array('relasi', $cols, true);
        $hasIdUser = in_array('id_user', $cols, true);

        $selectCols = [
            'idt',
            'tgl_transaksi',
            'rekening_debit',
            'rekening_kredit',
            'jumlah',
        ];
        if ($hasUrutan) {
            $selectCols[] = 'urutan';
        }
        if ($hasIdtp) {
            $selectCols[] = 'idtp';
        }
        if ($hasIdPinj) {
            $selectCols[] = 'id_pinj';
        }
        if ($hasIdPinjI) {
            $selectCols[] = 'id_pinj_i';
        }
        if ($hasKeterangan) {
            $selectCols[] = 'keterangan_transaksi';
        }
        if ($hasRelasi) {
            $selectCols[] = 'relasi';
        }
        if ($hasIdUser) {
            $selectCols[] = 'id_user';
        }

        $selectSql = implode(', ', $selectCols);

        $offset = 0;
        while (true) {
            $sql = "SELECT {$selectSql} FROM `{$table}` WHERE 1=1";
            if ($hasDeletedAt) {
                $sql .= ' AND deleted_at IS NULL';
            }
            $bindings = [];
            if ($fromDate !== null && $fromDate !== '') {
                $sql .= ' AND tgl_transaksi >= ?';
                $bindings[] = $fromDate;
            }
            if ($toDate !== null && $toDate !== '') {
                $sql .= ' AND tgl_transaksi <= ?';
                $bindings[] = $toDate;
            }
            $sql .= ' ORDER BY idt ASC LIMIT ? OFFSET ?';
            $bindings[] = $chunk;
            $bindings[] = $offset;

            $rows = $this->legacy->select($sql, $bindings);
            if ($rows === []) {
                break;
            }

            yield $rows;

            if (count($rows) < $chunk) {
                break;
            }
            $offset += $chunk;
        }
    }

    /**
     * @return list<object>
     */
    public function openings(string $suffix): array
    {
        $table = $this->legacy->saldoTable($suffix);
        $this->legacy->assertSafeTableName($table);
        if (! $this->legacy->tableExists($table)) {
            return [];
        }

        return $this->legacy->select(
            "SELECT kode_akun, tahun, bulan, debit, kredit
             FROM `{$table}`
             WHERE CAST(bulan AS UNSIGNED) = 0
             ORDER BY tahun, kode_akun",
        );
    }

    /**
     * @return list<object>
     */
    public function monthlyBalances(string $suffix): array
    {
        $table = $this->legacy->saldoTable($suffix);
        $this->legacy->assertSafeTableName($table);
        if (! $this->legacy->tableExists($table)) {
            return [];
        }

        return $this->legacy->select(
            "SELECT kode_akun, tahun, bulan, debit, kredit
             FROM `{$table}`
             WHERE CAST(bulan AS UNSIGNED) BETWEEN 1 AND 12
             ORDER BY tahun, CAST(bulan AS UNSIGNED), kode_akun",
        );
    }

    public function activeTransaksiCount(string $suffix, ?string $fromDate = null, ?string $toDate = null): int
    {
        $table = $this->legacy->transaksiTable($suffix);
        $this->legacy->assertSafeTableName($table);
        $cols = array_map(static fn (object $c): string => (string) ($c->COLUMN_NAME ?? ''), $this->legacy->columns($table));
        $hasDeletedAt = in_array('deleted_at', $cols, true);

        $sql = "SELECT COUNT(*) AS c FROM `{$table}` WHERE 1=1";
        if ($hasDeletedAt) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $bindings = [];
        if ($fromDate !== null && $fromDate !== '') {
            $sql .= ' AND tgl_transaksi >= ?';
            $bindings[] = $fromDate;
        }
        if ($toDate !== null && $toDate !== '') {
            $sql .= ' AND tgl_transaksi <= ?';
            $bindings[] = $toDate;
        }
        $row = $this->legacy->selectOne($sql, $bindings);

        return (int) ($row->c ?? 0);
    }

    /**
     * @return array{min: string|null, max: string|null}
     */
    public function dateRange(string $suffix, ?string $fromDate = null, ?string $toDate = null): array
    {
        $table = $this->legacy->transaksiTable($suffix);
        $this->legacy->assertSafeTableName($table);
        $cols = array_map(static fn (object $c): string => (string) ($c->COLUMN_NAME ?? ''), $this->legacy->columns($table));
        $hasDeletedAt = in_array('deleted_at', $cols, true);

        $sql = "SELECT MIN(tgl_transaksi) AS min_date, MAX(tgl_transaksi) AS max_date FROM `{$table}` WHERE 1=1";
        if ($hasDeletedAt) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $bindings = [];
        if ($fromDate !== null && $fromDate !== '') {
            $sql .= ' AND tgl_transaksi >= ?';
            $bindings[] = $fromDate;
        }
        if ($toDate !== null && $toDate !== '') {
            $sql .= ' AND tgl_transaksi <= ?';
            $bindings[] = $toDate;
        }
        $row = $this->legacy->selectOne($sql, $bindings);

        return [
            'min' => $row?->min_date !== null ? (string) $row->min_date : null,
            'max' => $row?->max_date !== null ? (string) $row->max_date : null,
        ];
    }
}
