<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Migration\Accounting\AccountingMigrationPipeline;
use App\Domain\Migration\Accounting\LegacyCoaImporter;
use App\Http\Controllers\Admin\MigrationController;
use App\Models\Platform\CutoverRun;
use App\Models\Platform\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class SimakMigrationTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->pointLegacyAtTenant();
        $this->createSimakFixtureTables();
        $this->seedSimakFixtureData();
    }

    protected function tearDown(): void
    {
        $this->dropSimakFixtureTables();
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_variant_resolution_from_jenis_akun_in_resolve_legacy_tenants(): void
    {
        $controller = app(MigrationController::class);
        $tenants = $controller->resolveLegacyTenants();

        self::assertCount(3, $tenants);

        // Usaha 1: jenis_akun = 5 -> standard
        $u1 = collect($tenants)->firstWhere('legacy_id', 1);
        self::assertNotNull($u1);
        self::assertSame('BUMDes Maju Bersama', $u1['legacy_name']);
        self::assertSame(5, $u1['jenis_akun']);
        self::assertSame('Standard', $u1['jenis_akun_label']);
        self::assertSame('standard', $u1['coa_variant']);
        self::assertSame('33.01.01.2001', $u1['legacy_code']);

        // Usaha 2: jenis_akun = 7 -> trading
        $u2 = collect($tenants)->firstWhere('legacy_id', 2);
        self::assertNotNull($u2);
        self::assertSame('Unit Usaha Perdagangan Sembako', $u2['legacy_name']);
        self::assertSame(7, $u2['jenis_akun']);
        self::assertSame('Trading', $u2['jenis_akun_label']);
        self::assertSame('trading', $u2['coa_variant']);

        // Usaha 3: jenis_akun = 8 -> cooperative
        $u3 = collect($tenants)->firstWhere('legacy_id', 3);
        self::assertNotNull($u3);
        self::assertSame('Koperasi Simpan Pinjam Sejahtera', $u3['legacy_name']);
        self::assertSame(8, $u3['jenis_akun']);
        self::assertSame('Cooperative', $u3['jenis_akun_label']);
        self::assertSame('cooperative', $u3['coa_variant']);
    }

    public function test_import_coa_jenis_5_standard_from_rekening_1(): void
    {
        $importer = app(LegacyCoaImporter::class);
        $result = $importer->import(suffix: '1', dryRun: false, reset: true);

        self::assertSame('legacy', $result['source']);
        self::assertSame('standard', $result['variant']);
        self::assertGreaterThan(0, $result['inserted']);

        // Level 1 asset
        $acc1 = Account::query()->where('code', '1')->first();
        self::assertNotNull($acc1);
        self::assertSame('Aset', $acc1->name);
        self::assertSame('D', $acc1->normal_balance);
        self::assertSame(1, $acc1->level);
        self::assertFalse($acc1->is_postable);

        // Level 4 Kas (rekening_1) with jenis_mutasi = 'debet'
        $kas = Account::query()->where('code', '1.1.01.01')->first();
        self::assertNotNull($kas);
        self::assertSame('Kas Tunai Utama', $kas->name);
        self::assertSame('D', $kas->normal_balance);
        self::assertTrue($kas->is_postable);
        self::assertTrue($kas->is_active);

        // Level 4 Tabungan Nonaktif with tgl_nonaktif in past
        $nonaktif = Account::query()->where('code', '1.1.01.02')->first();
        self::assertNotNull($nonaktif);
        self::assertFalse($nonaktif->is_active);
        self::assertSame('2020-01-01', $nonaktif->deactivated_at?->format('Y-m-d'));

        // Level 4 Hutang with jenis_mutasi = 'kredit'
        $hutang = Account::query()->where('code', '2.1.01.01')->first();
        self::assertNotNull($hutang);
        self::assertSame('Hutang Usaha', $hutang->name);
        self::assertSame('C', $hutang->normal_balance);
        self::assertTrue($hutang->is_postable);
    }

    public function test_import_coa_jenis_7_trading_from_accounts_2(): void
    {
        $importer = app(LegacyCoaImporter::class);
        $result = $importer->import(suffix: '2', dryRun: false, reset: true);

        self::assertSame('legacy', $result['source']);
        self::assertSame('trading', $result['variant']);

        // Account from accounts_2
        $persediaan = Account::query()->where('code', '1.1.04.01')->first();
        self::assertNotNull($persediaan);
        self::assertSame('Persediaan Barang Dagang', $persediaan->name);
        self::assertSame('D', $persediaan->normal_balance);
        self::assertTrue($persediaan->is_postable);

        // HPP account from akun_level_1s / accounts_2
        $hpp = Account::query()->where('code', '5.1.01.01')->first();
        self::assertNotNull($hpp);
        self::assertSame('Harga Pokok Penjualan', $hpp->name);
        self::assertSame('D', $hpp->normal_balance);
        self::assertTrue($hpp->is_postable);
    }

    public function test_mapping_saldo_bulan_0_to_opening_and_bulan_n_to_monthly(): void
    {
        // Seed fiscal periods for 2026
        for ($m = 1; $m <= 12; $m++) {
            FiscalPeriod::query()->create([
                'fiscal_year' => 2026,
                'fiscal_month' => $m,
                'starts_at' => sprintf('2026-%02d-01', $m),
                'ends_at' => sprintf('2026-%02d-%02d', $m, $m === 2 ? 28 : ($m === 4 || $m === 6 || $m === 9 || $m === 11 ? 30 : 31)),
                'status' => 'open',
            ]);
        }

        // Import COA first
        app(LegacyCoaImporter::class)->import(suffix: '1', dryRun: false, reset: true);

        // Run accounting migration pipeline
        $pipeline = app(AccountingMigrationPipeline::class);
        $result = $pipeline->run(
            suffix: '1',
            dryRun: false,
            chunk: 100,
            fromDate: '2026-01-01',
            toDate: '2026-12-31',
            failFast: true,
            skipOpenings: false,
            skipJournals: false,
            skipRecalc: false,
            skipReconcile: true,
        );

        self::assertSame('completed', $result['status'], json_encode($result));
        self::assertSame(2, $result['inserted_openings']); // 2 accounts in bulan 0
        self::assertSame(2, $result['inserted_monthly']);  // 2 records in bulan 1 & 2
        self::assertSame(1, $result['inserted_journals']); // 1 transaction

        // Check account_opening_balances (bulan = 0)
        $openings = DB::connection('tenant')->table('account_opening_balances')->get();
        self::assertCount(2, $openings);

        $kasOpening = $openings->firstWhere('account_row_id', Account::query()->where('code', '1.1.01.01')->value('row_id'));
        self::assertNotNull($kasOpening);
        self::assertEquals(5000000.00, (float) $kasOpening->debit);
        self::assertEquals(0.00, (float) $kasOpening->credit);

        // Check account_monthly_balances (recalculated/inserted)
        $monthlyKas = DB::connection('tenant')->table('account_monthly_balances')
            ->where('account_row_id', Account::query()->where('code', '1.1.01.01')->value('row_id'))
            ->where('fiscal_year', 2026)
            ->where('fiscal_month', 1)
            ->first();
        self::assertNotNull($monthlyKas);
    }

    public function test_migration_controller_auto_provision_tenant_from_usaha(): void
    {
        $superadmin = User::query()->where('username', 'superadmin')->first();
        if ($superadmin === null) {
            $superadmin = User::query()->create([
                'public_id' => '01H00000000000000000000001',
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'email' => 'superadmin@example.com',
                'password' => bcrypt('password'),
                'status' => 'active',
            ]);
        }

        $request = Request::create('/admin/migrations', 'POST', [
            'legacy_id' => 2, // Trading unit
            'auto_provision' => 1,
            'is_dry_run' => 0,
            'run_immediately' => 0,
        ]);
        $request->setUserResolver(fn () => $superadmin);

        $controller = app(MigrationController::class);
        $response = $controller->store($request);

        self::assertSame(302, $response->getStatusCode());

        // Verify tenant was created with coa_variant = trading
        $createdTenant = Tenant::query()->where('district_code', '33.01.01.2002')->first();
        self::assertNotNull($createdTenant);
        self::assertSame('trading', $createdTenant->coa_variant);

        // Verify CutoverRun was registered
        $run = CutoverRun::query()->where('tenant_id', $createdTenant->row_id)->first();
        self::assertNotNull($run);
        self::assertSame('2', (string) $run->suffix);
        self::assertSame('trading', $run->options['coa_variant']);
    }

    private function pointLegacyAtTenant(): void
    {
        $tenant = config('database.connections.tenant');
        config([
            'database.connections.legacy' => array_merge($tenant, [
                'name' => 'legacy',
            ]),
        ]);
        DB::purge('legacy');
    }

    private function createSimakFixtureTables(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('usaha');
        $schema->dropIfExists('akun_level_1');
        $schema->dropIfExists('akun_level_1s');
        $schema->dropIfExists('akun_level_1_koperasi');
        $schema->dropIfExists('akun_level_2');
        $schema->dropIfExists('akun_level_2s');
        $schema->dropIfExists('akun_level_2_koperasi');
        $schema->dropIfExists('akun_1');
        $schema->dropIfExists('akun_2');
        $schema->dropIfExists('rekening_1');
        $schema->dropIfExists('accounts_2');
        $schema->dropIfExists('saldo_1');
        $schema->dropIfExists('transaksi_1');

        $schema->create('usaha', function ($table): void {
            $table->increments('id');
            $table->string('nama_usaha', 150);
            $table->unsignedTinyInteger('jenis_akun')->default(5);
            $table->string('kd_desa', 50)->nullable();
        });

        $schema->create('akun_level_1', function ($table): void {
            $table->increments('id');
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
            $table->unsignedTinyInteger('lev1')->default(1);
        });

        $schema->create('akun_level_1s', function ($table): void {
            $table->increments('id');
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
            $table->unsignedTinyInteger('lev1')->default(1);
        });

        $schema->create('akun_level_1_koperasi', function ($table): void {
            $table->increments('id');
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
            $table->unsignedTinyInteger('lev1')->default(1);
        });

        $schema->create('akun_level_2', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->default(0);
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
        });

        $schema->create('akun_level_2s', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->default(0);
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
        });

        $schema->create('akun_level_2_koperasi', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->default(0);
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
        });

        $schema->create('akun_1', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->default(0);
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
        });

        $schema->create('akun_2', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->default(0);
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
        });

        $schema->create('rekening_1', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->default(0);
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
            $table->string('jenis_mutasi', 20)->default('debet');
            $table->date('tgl_nonaktif')->nullable();
        });

        $schema->create('accounts_2', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->default(0);
            $table->string('kode_akun', 50);
            $table->string('nama_akun', 150);
            $table->string('jenis_mutasi', 20)->default('debet');
            $table->date('tgl_nonaktif')->nullable();
        });

        $schema->create('saldo_1', function ($table): void {
            $table->increments('id');
            $table->string('kode_akun', 50);
            $table->unsignedSmallInteger('tahun');
            $table->string('bulan', 4);
            $table->string('debit', 100)->default('0');
            $table->string('kredit', 100)->default('0');
        });

        $schema->create('transaksi_1', function ($table): void {
            $table->increments('idt');
            $table->date('tgl_transaksi');
            $table->string('rekening_debit', 50);
            $table->string('rekening_kredit', 50);
            $table->string('jumlah', 100);
            $table->unsignedInteger('urutan')->default(0);
            $table->integer('idtp')->default(0);
            $table->string('keterangan_transaksi', 255)->nullable();
        });
    }

    private function dropSimakFixtureTables(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('usaha');
        $schema->dropIfExists('akun_level_1');
        $schema->dropIfExists('akun_level_1s');
        $schema->dropIfExists('akun_level_1_koperasi');
        $schema->dropIfExists('akun_level_2');
        $schema->dropIfExists('akun_level_2s');
        $schema->dropIfExists('akun_level_2_koperasi');
        $schema->dropIfExists('akun_1');
        $schema->dropIfExists('akun_2');
        $schema->dropIfExists('rekening_1');
        $schema->dropIfExists('accounts_2');
        $schema->dropIfExists('saldo_1');
        $schema->dropIfExists('transaksi_1');
    }

    private function seedSimakFixtureData(): void
    {
        // 1. Usaha
        DB::connection('tenant')->table('usaha')->insert([
            ['id' => 1, 'nama_usaha' => 'BUMDes Maju Bersama', 'jenis_akun' => 5, 'kd_desa' => '33.01.01.2001'],
            ['id' => 2, 'nama_usaha' => 'Unit Usaha Perdagangan Sembako', 'jenis_akun' => 7, 'kd_desa' => '33.01.01.2002'],
            ['id' => 3, 'nama_usaha' => 'Koperasi Simpan Pinjam Sejahtera', 'jenis_akun' => 8, 'kd_desa' => '33.01.01.2003'],
        ]);

        // 2. Akun Level 1 (Standard)
        DB::connection('tenant')->table('akun_level_1')->insert([
            ['id' => 1, 'kode_akun' => '1', 'nama_akun' => 'Aset', 'lev1' => 1],
            ['id' => 2, 'kode_akun' => '2', 'nama_akun' => 'Kewajiban', 'lev1' => 2],
            ['id' => 3, 'kode_akun' => '3', 'nama_akun' => 'Ekuitas', 'lev1' => 3],
            ['id' => 4, 'kode_akun' => '4', 'nama_akun' => 'Pendapatan', 'lev1' => 4],
            ['id' => 5, 'kode_akun' => '5', 'nama_akun' => 'Beban', 'lev1' => 5],
        ]);

        // Akun Level 1s (Trading)
        DB::connection('tenant')->table('akun_level_1s')->insert([
            ['id' => 1, 'kode_akun' => '1', 'nama_akun' => 'Aset Trading', 'lev1' => 1],
            ['id' => 2, 'kode_akun' => '2', 'nama_akun' => 'Kewajiban Trading', 'lev1' => 2],
            ['id' => 3, 'kode_akun' => '3', 'nama_akun' => 'Ekuitas Trading', 'lev1' => 3],
            ['id' => 4, 'kode_akun' => '4', 'nama_akun' => 'Penjualan', 'lev1' => 4],
            ['id' => 5, 'kode_akun' => '5', 'nama_akun' => 'Beban Pokok Penjualan', 'lev1' => 5],
        ]);

        // Akun Level 2
        DB::connection('tenant')->table('akun_level_2')->insert([
            ['id' => 1, 'parent_id' => 1, 'kode_akun' => '1.1', 'nama_akun' => 'Aset Lancar'],
            ['id' => 2, 'parent_id' => 2, 'kode_akun' => '2.1', 'nama_akun' => 'Kewajiban Jangka Pendek'],
            ['id' => 3, 'parent_id' => 3, 'kode_akun' => '3.1', 'nama_akun' => 'Modal'],
        ]);

        DB::connection('tenant')->table('akun_level_2s')->insert([
            ['id' => 1, 'parent_id' => 1, 'kode_akun' => '1.1', 'nama_akun' => 'Aset Lancar Perdagangan'],
            ['id' => 2, 'parent_id' => 5, 'kode_akun' => '5.1', 'nama_akun' => 'Harga Pokok Penjualan'],
        ]);

        // Akun Level 3 (akun_1)
        DB::connection('tenant')->table('akun_1')->insert([
            ['id' => 1, 'parent_id' => 1, 'kode_akun' => '1.1.01', 'nama_akun' => 'Kas dan Setara Kas'],
            ['id' => 2, 'parent_id' => 2, 'kode_akun' => '2.1.01', 'nama_akun' => 'Hutang Usaha Lancar'],
            ['id' => 3, 'parent_id' => 3, 'kode_akun' => '3.1.01', 'nama_akun' => 'Modal Disetor'],
        ]);

        // Rekening 1 (Posting chart for suffix 1)
        DB::connection('tenant')->table('rekening_1')->insert([
            ['id' => 1, 'parent_id' => 1, 'kode_akun' => '1.1.01.01', 'nama_akun' => 'Kas Tunai Utama', 'jenis_mutasi' => 'debet', 'tgl_nonaktif' => null],
            ['id' => 2, 'parent_id' => 1, 'kode_akun' => '1.1.01.02', 'nama_akun' => 'Tabungan Lama Nonaktif', 'jenis_mutasi' => 'debet', 'tgl_nonaktif' => '2020-01-01'],
            ['id' => 3, 'parent_id' => 2, 'kode_akun' => '2.1.01.01', 'nama_akun' => 'Hutang Usaha', 'jenis_mutasi' => 'kredit', 'tgl_nonaktif' => null],
            ['id' => 4, 'parent_id' => 3, 'kode_akun' => '3.1.01.01', 'nama_akun' => 'Modal Awal', 'jenis_mutasi' => 'kredit', 'tgl_nonaktif' => null],
        ]);

        // Accounts 2 (Posting chart for suffix 2 trading)
        DB::connection('tenant')->table('accounts_2')->insert([
            ['id' => 1, 'parent_id' => 1, 'kode_akun' => '1.1.04.01', 'nama_akun' => 'Persediaan Barang Dagang', 'jenis_mutasi' => 'debet', 'tgl_nonaktif' => null],
            ['id' => 2, 'parent_id' => 2, 'kode_akun' => '5.1.01.01', 'nama_akun' => 'Harga Pokok Penjualan', 'jenis_mutasi' => 'debet', 'tgl_nonaktif' => null],
        ]);

        // Saldo 1
        DB::connection('tenant')->table('saldo_1')->insert([
            ['id' => 1, 'kode_akun' => '1.1.01.01', 'tahun' => 2026, 'bulan' => '0', 'debit' => '5000000', 'kredit' => '0'],
            ['id' => 2, 'kode_akun' => '3.1.01.01', 'tahun' => 2026, 'bulan' => '0', 'debit' => '0', 'kredit' => '5000000'],
            ['id' => 3, 'kode_akun' => '1.1.01.01', 'tahun' => 2026, 'bulan' => '1', 'debit' => '6000000', 'kredit' => '500000'],
            ['id' => 4, 'kode_akun' => '1.1.01.01', 'tahun' => 2026, 'bulan' => '2', 'debit' => '7500000', 'kredit' => '1000000'],
        ]);

        // Transaksi 1
        DB::connection('tenant')->table('transaksi_1')->insert([
            'idt' => 1,
            'tgl_transaksi' => '2026-01-15',
            'rekening_debit' => '1.1.01.01',
            'rekening_kredit' => '3.1.01.01',
            'jumlah' => '1000000',
            'urutan' => 0,
            'idtp' => 0,
            'keterangan_transaksi' => 'Setoran modal tambahan',
        ]);
    }
}
