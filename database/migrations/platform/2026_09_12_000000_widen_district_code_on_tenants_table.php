<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Lebar kolom tenants.district_code dari varchar(6) menjadi varchar(20).
 *
 * Kode `kd_desa` pada database legacy simak memakai penomoran lengkap
 * desa/kelurahan (contoh: 33.08.19.2001, 13 karakter) sehingga gagal tersimpan
 * pada kolom 6 karakter saat auto-provisioning tenant di MigrationController.
 */
return new class extends Migration
{
    private const TABLE = 'tenants';

    private const COLUMN = 'district_code';

    private const INDEX_NAME = 'uq_tenants_district_code';

    private const NEW_LENGTH = 20;

    private const OLD_LENGTH = 6;

    private function schema(): Builder
    {
        return Schema::connection((string) config('tenancy.platform_connection', 'platform'));
    }

    public function up(): void
    {
        $schema = $this->schema();

        if (! $schema->hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if ($this->columnLength() >= self::NEW_LENGTH) {
            return;
        }

        $this->widenTo(self::NEW_LENGTH);

        // SQLite tidak menyimpan batas panjang varchar, jadi indeks unik dijamin
        // tetap ada setelah tabel dibangun ulang oleh ->change().
        $this->ensureUniqueIndex();
    }

    public function down(): void
    {
        $schema = $this->schema();

        if (! $schema->hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if ($this->columnLength() <= self::OLD_LENGTH) {
            return;
        }

        $tooLong = $schema->getConnection()->table(self::TABLE)
            ->whereNotNull(self::COLUMN)
            ->whereRaw('length('.self::COLUMN.') > ?', [self::OLD_LENGTH])
            ->count();

        if ($tooLong > 0) {
            throw new RuntimeException(sprintf(
                'Cannot narrow tenants.district_code back to %d characters: %d tenant(s) still store a longer '
                .'village code. Re-point them to a 6-digit kecamatan code first.',
                self::OLD_LENGTH,
                $tooLong,
            ));
        }

        $this->widenTo(self::OLD_LENGTH);

        $this->ensureUniqueIndex();
    }

    private function widenTo(int $length): void
    {
        $schema = $this->schema();

        $schema->table(self::TABLE, function (Blueprint $table) use ($length): void {
            $table->string(self::COLUMN, $length)->nullable()->change();
        });
    }

    private function ensureUniqueIndex(): void
    {
        $schema = $this->schema();

        $hasIndex = $schema->hasIndex(self::TABLE, self::INDEX_NAME)
            || $schema->hasIndex(self::TABLE, 'tenants_'.self::COLUMN.'_unique');

        if ($hasIndex) {
            return;
        }

        $schema->table(self::TABLE, function (Blueprint $table): void {
            $table->unique(self::COLUMN, self::INDEX_NAME);
        });
    }

    /**
     * Panjang kolom seperti dilaporkan driver. Sebagian driver (SQLite) tidak
     * mengekspos batas varchar, sehingga bernilai 0 dan seluruh pemeriksaan
     * panjang dilewati.
     */
    private function columnLength(): int
    {
        foreach ($this->schema()->getColumns(self::TABLE) as $column) {
            if (($column['name'] ?? null) !== self::COLUMN) {
                continue;
            }

            $definition = (string) ($column['type'] ?? '');

            if (preg_match('/\((\d+)\)/', $definition, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
};
