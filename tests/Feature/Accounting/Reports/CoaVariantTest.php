<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Accounting\Services\Reports\IncomeStatementService;
use App\Tenancy\Services\Coa\Templates\CooperativeCoaTemplate;
use App\Tenancy\Services\Coa\Templates\StandardCoaTemplate;
use App\Tenancy\Services\Coa\Templates\TradingCoaTemplate;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class CoaVariantTest extends TestCase
{
    use BuildsTenantTestDatabase;

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_template_account_counts(): void
    {
        self::assertCount(172, (new StandardCoaTemplate)->rows());
        self::assertCount(62, (new TradingCoaTemplate)->rows());
        self::assertCount(47, (new CooperativeCoaTemplate)->rows());
    }

    public function test_trading_template_has_parity_accounts(): void
    {
        $codes = array_column((new TradingCoaTemplate)->rows(), 'code');

        foreach ([
            '4.1.01.01', '4.1.01.02', '4.1.01.03', '4.1.01.06',
            '1.1.03.01', '1.1.03.02',
            '5.1.01.01', '5.1.01.02', '5.1.01.03', '5.1.01.06',
            '7.4.01.01',
        ] as $code) {
            self::assertContains($code, $codes);
        }
    }

    public function test_cooperative_template_has_phu_structure(): void
    {
        $codes = array_column((new CooperativeCoaTemplate)->rows(), 'code');

        foreach (['3.1.01.01', '3.1.02.01', '3.1.03.01', '3.3.03.01'] as $code) {
            self::assertContains($code, $codes);
        }
    }

    public function test_standard_income_statement_surplus(): void
    {
        $this->seedCoaAndPeriod('standard', [
            ['4.1.01.01', 'revenue', 'C', ['ytd' => 500000]],
            ['5.1.02.01', 'expense', 'D', ['ytd' => 100000]],
            ['5.4.01.01', 'expense', 'D', ['ytd' => 50000]],
        ]);

        $report = app(IncomeStatementService::class)->build(2026, 7);

        self::assertSame('standard', app(IncomeStatementService::class)->coaVariant());
        self::assertSame('Laporan Laba Rugi', $report['title']);
        self::assertEqualsWithDelta(400000.0, $report['summary']['before_tax']['ytd'], 0.02);
        self::assertEqualsWithDelta(400000.0, $report['summary']['after_tax']['ytd'], 0.02);
    }

    public function test_trading_income_statement_uses_hpp_account(): void
    {
        $this->seedCoaAndPeriod('trading', [
            ['4.1.01.01', 'revenue', 'C', ['ytd' => 900000]],
            ['4.1.01.02', 'revenue', 'C', ['ytd' => -100000]],
            ['5.1.01.01', 'expense', 'D', ['ytd' => 400000]],
            ['5.1.02.01', 'expense', 'D', ['ytd' => 150000]],
            ['7.4.01.01', 'expense', 'D', ['ytd' => 50000]],
        ]);

        $report = app(IncomeStatementService::class)->build(2026, 7);

        self::assertSame('trading', app(IncomeStatementService::class)->coaVariant());
        self::assertEqualsWithDelta(400000.0, $report['summary']['operating']['ytd'], 0.02);
        self::assertEqualsWithDelta(250000.0, $report['summary']['before_tax']['ytd'], 0.02);
        self::assertEqualsWithDelta(200000.0, $report['summary']['after_tax']['ytd'], 0.02);
    }

    public function test_trading_income_statement_falls_back_to_inventory_formula(): void
    {
        $this->seedCoaAndPeriod('trading', [
            ['1.1.03.01', 'asset', 'D', ['prior' => 100000, 'ytd' => 200000]],
            ['1.1.03.02', 'asset', 'D', ['ytd' => 500000]],
            ['5.1.01.02', 'expense', 'D', ['ytd' => -50000]],
            ['4.1.01.01', 'revenue', 'C', ['ytd' => 800000]],
            ['5.1.02.01', 'expense', 'D', ['ytd' => 100000]],
        ]);

        $report = app(IncomeStatementService::class)->build(2026, 7);

        self::assertEqualsWithDelta(900000.0, $report['summary']['operating']['ytd'], 0.02);
        self::assertEqualsWithDelta(800000.0, $report['summary']['before_tax']['ytd'], 0.02);
    }

    public function test_cooperative_income_statement_builds_phu(): void
    {
        $this->seedCoaAndPeriod('cooperative', [
            ['4.1.01.01', 'revenue', 'C', ['ytd' => 600000]],
            ['5.1.02.01', 'expense', 'D', ['ytd' => 150000]],
        ]);

        $report = app(IncomeStatementService::class)->build(2026, 7);

        self::assertSame('cooperative', app(IncomeStatementService::class)->coaVariant());
        self::assertSame('Perhitungan Hasil Usaha', $report['title']);
        self::assertEqualsWithDelta(450000.0, $report['summary']['after_tax']['ytd'], 0.02);
    }

    private function seedCoaAndPeriod(string $variant, array $accounts): void
    {
        $this->rebuildTenantTestDatabases();
        $this->testTenant->forceFill(['coa_variant' => $variant])->save();
        app(TenantContext::class)->initialize($this->testTenant, $this->testPlacement, $this->testShard);

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 7,
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'status' => 'open',
        ]);

        $createdAt = CarbonImmutable::parse('2026-07-01')->startOfDay();
        $created = [];
        $poster = app(JournalPostingService::class);

        foreach ($accounts as [$code, $type, $normal, $values]) {
            $account = Account::query()->create([
                'code' => $code,
                'name' => ucfirst($code),
                'account_type' => $type,
                'normal_balance' => $normal,
                'level' => 4,
                'is_postable' => true,
                'is_active' => true,
                'created_at' => $createdAt,
            ]);
            $created[$code] = $account;
        }

        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-07-10',
            'sequence_number' => 1,
            'description' => 'Variant scenario',
            'status' => 'draft',
        ]);
        $lineNumber = 1;
        $lines = [];

        foreach ($accounts as [$code, $type, $normal, $values]) {
            $amount = (float) $values['ytd'];
            if ($amount === 0.0) {
                continue;
            }

            $isDebit = $normal === 'D' ? $amount > 0 : $amount < 0;
            $absolute = abs($amount);
            $lines[] = [
                'account' => $created[$code],
                'debit' => $isDebit ? $absolute : 0.0,
                'credit' => $isDebit ? 0.0 : $absolute,
            ];
        }

        $cash = Account::query()->create([
            'code' => '1.1.01.99',
            'name' => 'Cash Control',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => $createdAt,
        ]);

        $debitTotal = round(array_sum(array_column($lines, 'debit')), 2);
        $creditTotal = round(array_sum(array_column($lines, 'credit')), 2);
        $difference = round($debitTotal - $creditTotal, 2);
        if ($difference !== 0.0) {
            $lines[] = [
                'account' => $cash,
                'debit' => $difference < 0 ? abs($difference) : 0.0,
                'credit' => $difference > 0 ? $difference : 0.0,
            ];
        }

        foreach ($lines as $line) {
            JournalLine::query()->create([
                'journal_entry_row_id' => $entry->row_id,
                'line_number' => $lineNumber++,
                'account_row_id' => $line['account']->row_id,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ]);
        }

        $poster->post($entry, 1);
    }
}
