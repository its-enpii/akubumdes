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
        return Schema::connection((string) config('tenancy.tenant_connection', 'tenant'));
    }

    private function addTenantIdentity(Blueprint $table, bool $publicId = false): void
    {
        $table->bigIncrements('row_id');
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('id');

        if ($publicId) {
            $table->char('public_id', 26)->unique();
        }

        $table->unique(['tenant_id', 'row_id']);
        $table->unique(['tenant_id', 'id']);
        $table->foreign('tenant_id')->references('id')->on('tenant_registry')->restrictOnDelete();
    }

    public function up(): void
    {
        $schema = $this->schema();

        $schema->create('organization_profiles', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->string('legal_name', 200);
            $table->string('short_name', 100)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->date('operational_start_date')->nullable();
            $table->timestamps();

            $table->unique('tenant_id', 'uq_org_profiles_tenant');
        });

        $schema->create('organization_units', function (Blueprint $table): void {
            $this->addTenantIdentity($table);
            $table->unsignedBigInteger('parent_row_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 180);
            $table->string('type', 30);
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'uq_org_units_code');
            $table->index(['tenant_id', 'parent_row_id'], 'ix_org_units_parent');
            $table->foreign(['tenant_id', 'parent_row_id'], 'fk_org_units_parent')
                ->references(['tenant_id', 'row_id'])
                ->on('organization_units')
                ->restrictOnDelete();
        });

        foreach ([
            'business_types' => ['code', 'name'],
            'activity_types' => ['code', 'name'],
        ] as $tableName => $columns) {
            $schema->create($tableName, function (Blueprint $table) use ($columns, $tableName): void {
                $this->addTenantIdentity($table);
                $table->string($columns[0], 50);
                $table->string($columns[1], 150);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['tenant_id', 'code'], 'uq_'.substr($tableName, 0, 20).'_code');
            });
        }

    }

    public function down(): void
    {
        $schema = $this->schema();

        foreach ([
            'activity_types',
            'business_types',
            'organization_units',
            'organization_profiles',
        ] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
