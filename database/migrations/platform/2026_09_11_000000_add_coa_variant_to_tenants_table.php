<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function schema(): Builder
    {
        return Schema::connection((string) config('tenancy.platform_connection', 'platform'));
    }

    public function up(): void
    {
        $schema = $this->schema();

        $schema->table('tenants', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn('tenants', 'coa_variant')) {
                $table->string('coa_variant', 20)->default('standard')->index()->after('status');
            }
        });
    }

    public function down(): void
    {
        $schema = $this->schema();

        $schema->table('tenants', function (Blueprint $table) use ($schema): void {
            if ($schema->hasColumn('tenants', 'coa_variant')) {
                $table->dropColumn('coa_variant');
            }
        });
    }
};
