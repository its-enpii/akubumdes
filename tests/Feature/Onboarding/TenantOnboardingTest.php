<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Onboarding\Services\TenantOnboardingService;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantOnboardingTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private TenantOnboardingService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->service = app(TenantOnboardingService::class);
        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Onboarding User',
            'email' => 'onboarding@example.test',
            'username' => 'onboarding_user',
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    public function test_can_save_balanced_opening_balances(): void
    {
        $kas = Account::query()->create([
            'code' => '1.1.01.01',
            'name' => 'Kas Kantor Test',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $modal = Account::query()->create([
            'code' => '3.1.01.01',
            'name' => 'Modal Test',
            'account_type' => 'equity',
            'normal_balance' => 'C',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $entry = $this->service->saveOpeningBalances([
            ['account_row_id' => (int) $kas->row_id, 'debit' => 5000000, 'credit' => 0],
            ['account_row_id' => (int) $modal->row_id, 'debit' => 0, 'credit' => 5000000],
        ], '2026-01-01', (int) $this->user->row_id);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertEquals('posted', $entry->status);
        $this->assertEquals(5000000, (float) $entry->lines()->sum('debit'));
        $this->assertCount(2, $entry->lines);
    }

    public function test_throws_exception_when_opening_balances_unbalanced(): void
    {
        $kas = Account::query()->create([
            'code' => '1.1.01.02',
            'name' => 'Kas Test 2',
            'account_type' => 'asset',
            'normal_balance' => 'D',
            'level' => 4,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Saldo awal tidak imbang');

        $this->service->saveOpeningBalances([
            ['account_row_id' => (int) $kas->row_id, 'debit' => 5000000, 'credit' => 0],
        ], '2026-01-01', (int) $this->user->row_id);
    }
}
