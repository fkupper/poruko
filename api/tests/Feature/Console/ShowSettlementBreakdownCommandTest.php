<?php

namespace Tests\Feature\Console;

use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('settlements')]
class ShowSettlementBreakdownCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testFailsWhenSettlementNotFound(): void
    {
        $this->artisan('settlements:show', ['settlement' => 99999])
            ->assertFailed()
            ->expectsOutputToContain('Settlement not found');
    }

    public function testDisplaysBreakdownForValidSettlement(): void
    {
        $ledger = Ledger::factory()->create([
            'name' => 'Test Ledger',
            'settlement_mode' => 'direct_p2p',
        ]);
        $user = User::factory()->create(['name' => 'Alice']);
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $personalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => 'personal',
            'name' => "Alice's Account",
        ]);
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => 'external',
            'owner_id' => null,
            'name' => 'External',
        ]);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => '2026-01-01',
            'incomes' => [['description' => 'Income', 'amount' => 100000]],
            'deductions' => [],
        ]);

        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $personalAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 10000,
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
            'description' => 'Groceries',
            'date' => '2026-02-15',
        ]);

        $settlement = Settlement::factory()->create([
            'ledger_id' => $ledger->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'executed_at' => null,
        ]);

        $exitCode = $this->withoutMockingConsoleOutput()->artisan('settlements:show', [
            'settlement' => $settlement->id,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Test Ledger', $output);
        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('100.00 €', $output);
        $this->assertStringContainsString('2026-02', $output);
        $this->assertStringContainsString('Groceries', $output);
        $this->assertStringContainsString("Alice's Account", $output);
        $this->assertStringContainsString('External', $output);
    }
}
