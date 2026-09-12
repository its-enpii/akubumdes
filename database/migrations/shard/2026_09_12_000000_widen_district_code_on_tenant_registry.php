<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Pasangan dari migration platform 2026_09_12_000000_widen_district_code_on_tenants_table.
 *
 * TenantRegistrySynchronizer menyalin tenants.district_code ke tenant_registry.district_code,
 * sehingga kode desa/kelurahan dari simak (contoh: 33.08.19.2001) juga harus muat di shard.
 */
return new class extends Migration
{
    private const TABLE = 'tenant_registry';

    private const COLUMN = 'district_code';

    private const INDEX_NAME = 'uq_tenant_registry_district_code';

    private const NEW_LENGTH = 20;

    private const OLD_LENGTH = 6;

    private function schema(): Builder
    {
        return Schema::connection((string) config('tenancy.tenant_connection', 'tenant'));
    }

    public function up(): void
    {
        $schema = $this->schema();

        if (! $schema->hasTable(self::TABLE) || ! $schema->hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if ($this->columnLength() < self::NEW_LENGTH) {
            $schema->table(self::TABLE, function (Blueprint $table): void {
                $table->string(self::COLUMN, self::NEW_LENGTH)->nullable()->change();
            });
        }

        $this->ensureUniqueIndex();
    }

    public function down(): void
    {
        $schema = $this->schema();

        if (! $schema->hasTable(self::TABLE) || ! $schema->hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if ($this->columnLength() > self::OLD_LENGTH) {
            $schema->table(self::TABLE, function (Blueprint $table): void {
                $table->string(self::COLUMN, self::OLD_LENGTH)->nullable()->change();
            });
        }

        $this->ensureUniqueIndex();
    }

    private function ensureUniqueIndex(): void
    {
        $schema = $this->schema();

        if ($schema->hasIndex(self::TABLE, self::INDEX_NAME)
            || $schema->hasIndex(self::TABLE, 'tenant_registry_'.self::COLUMN.'_unique')) {
            return;
        }

        $schema->table(self::TABLE, function (Blueprint $table): void {
            $table->unique(self::COLUMN, self::INDEX_NAME);
        });
    }

    private function columnLength(): int
    {
        foreach ($this->schema()->getColumns(self::TABLE) as $column) {
            if (($column['name'] ?? null) !== self::COLUMN) {
                continue;
            }

            if (preg_match('/\((\d+)\)/', (string) ($column['type'] ?? ''), $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
};
