<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_member_can_view_empty_dashboard(): void
    {
        $user = $this->createTenantMember();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('cards.0.key', 'cash')
                ->where('cards.0.value', 0)
                ->where('recent_journals', [])
                ->has('cards', 6)
                ->has('trend', 6)
                ->where('top_revenues', [])
                ->where('period', null)
                ->missing('counts')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token'));
    }

    public function test_dashboard_summarizes_posted_revenue_and_expense(): void
    {
        $user = $this->createTenantMember();
        $today = CarbonImmutable::today();
        $currentMonth = (int) $today->format('n');

        FiscalPeriod::query()->create([
            'fiscal_year' => (int) $today->format('Y'),
            'fiscal_month' => $currentMonth,
            'starts_at' => $today->startOfMonth()->toDateString(),
            'ends_at' => $today->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        $cash = Account::query()->create([
            'code' => '1.1.01.01',
            'name' => 'Kas Tunai',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $operationalRevenue = Account::query()->create([
            'code' => '4.1.01.01',
            'name' => 'Pendapatan Operasional',
            'account_type' => 'revenue',
            'normal_balance' => 'C',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $otherRevenue = Account::query()->create([
            'code' => '4.1.02.01',
            'name' => 'Pendapatan Lain',
            'account_type' => 'revenue',
            'normal_balance' => 'C',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);
        $expense = Account::query()->create([
            'code' => '5.1.01.01',
            'name' => 'Beban Operasional',
            'account_type' => 'expense',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->createPostedJournal(
            $today->toDateString(),
            [$cash, 300000.0, 0.0],
            [$expense, 100000.0, 0.0],
            [$operationalRevenue, 0.0, 150000.0],
            [$otherRevenue, 0.0, 250000.0],
        );
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('cards.0.key', 'cash')
                ->where('cards.0.value', 300000)
                ->where('cards.1.key', 'revenue_ytd')
                ->where('cards.1.value', 400000)
                ->where('cards.2.key', 'expense_ytd')
                ->where('cards.2.value', 100000)
                ->where('cards.3.key', 'surplus_ytd')
                ->where('cards.3.value', 300000)
                ->where('cards.3.tone', null)
                ->where('cards.4.key', 'assets_total')
                ->where('cards.4.value', 300000)
                ->where('cards.5.key', 'journals_month')
                ->where('cards.5.value', 1)
                ->where('period.fiscal_year', (int) $today->format('Y'))
                ->where('period.fiscal_month', $currentMonth)
                ->where('period.status', 'open')
                ->has('trend', 6)
                ->where('trend.5.key', $today->format('Y-m'))
                ->where('trend.5.revenue', 400000)
                ->where('trend.5.expense', 100000)
                ->where('trend.0.revenue', 0)
                ->where('trend.0.expense', 0)
                ->where('trend.1.revenue', 0)
                ->where('trend.1.expense', 0)
                ->where('trend.2.revenue', 0)
                ->where('trend.2.expense', 0)
                ->where('trend.3.revenue', 0)
                ->where('trend.3.expense', 0)
                ->where('trend.4.revenue', 0)
                ->where('trend.4.expense', 0)
                ->where('top_revenues.0.account_code', '4.1.02.01')
                ->where('top_revenues.0.amount', 250000)
                ->where('top_revenues.1.account_code', '4.1.01.01')
                ->where('top_revenues.1.amount', 150000)
                ->has('recent_journals', 1)
                ->has('recent_journals.0.row_id')
                ->where('recent_journals.0.amount', 400000));
    }

    public function test_authenticated_user_without_membership_is_forbidden(): void
    {
        $this->createTenantMember();

        $stranger = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Stranger',
            'email' => 'stranger@example.test',
            'username' => 'stranger_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->actingAs($stranger)->get('/dashboard')->assertForbidden();
    }

    private function createTenantMember(): User
    {
        $tenantDb = (string) config('database.connections.tenant.database');

        $shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Shard',
            'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
            'host' => (string) config('database.connections.tenant.host', '127.0.0.1'),
            'port' => (int) config('database.connections.tenant.port', 3306),
            'database_name' => $tenantDb,
            'credential_reference' => str_ends_with($tenantDb, '_test') ? 'test' : 'local',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Tenant',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => ['localhost']],
        ]);

        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => 'Dashboard User',
            'email' => 'dashboard@example.test',
            'username' => 'dashboard_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);
        Artisan::call('tenancy:sync-registry', ['--shard' => 'local']);

        config(['tenancy.local_tenant' => 'local']);
        app(TenantContext::class)->initialize($tenant, TenantPlacement::query()->first(), $shard);

        return $user;
    }

    private function createPostedJournal(
        string $transactionDate,
        array ...$lines,
    ): void {
        $entry = JournalEntry::query()->create([
            'transaction_date' => $transactionDate,
            'sequence_number' => 1,
            'description' => 'Dashboard enrichment fixture',
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        foreach ($lines as $index => [$account, $debit, $credit]) {
            JournalLine::query()->create([
                'journal_entry_row_id' => $entry->row_id,
                'line_number' => $index + 1,
                'account_row_id' => $account->row_id,
                'debit' => number_format($debit, 2, '.', ''),
                'credit' => number_format($credit, 2, '.', ''),
            ]);
        }
    }
}
