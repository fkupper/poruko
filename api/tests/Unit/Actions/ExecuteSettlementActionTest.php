<?php

namespace Tests\Unit\Actions;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\ExecuteSettlementAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(ExecuteSettlementAction::class)]
class ExecuteSettlementActionTest extends TestCase
{
    use RefreshDatabase;

    public function testCreatesSettlementRowForPeriod(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create([
            'settlement_mode' => 'direct_p2p',
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);
        $a = User::factory()->create(['name' => 'A']);
        $b = User::factory()->create(['name' => 'B']);
        $ledger->users()->attach($a->id, ['role' => 'admin']);
        $ledger->users()->attach($b->id, ['role' => 'member']);

        $aAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $a->id,
            'type' => AccountType::Personal->value,
        ]);
        $bAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $b->id,
            'type' => AccountType::Personal->value,
        ]);
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::External->value,
        ]);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $a->id,
            'valid_from' => '2026-01-01',
            'incomes' => [['description' => 'Income', 'amount' => 100000]],
            'deductions' => [],
        ]);
        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $b->id,
            'valid_from' => '2026-01-01',
            'incomes' => [['description' => 'Income', 'amount' => 100000]],
            'deductions' => [],
        ]);

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 10000,
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
            'description' => 'Shared expense',
            'date' => '2026-03-20',
        ]);

        // Act
        $action = $this->app->make(ExecuteSettlementAction::class);
        $settlement = $action->execute($ledger, '2026-03-31');

        // Assert
        $this->assertSame($ledger->id, $settlement->ledger_id);
        $this->assertSame('2026-03-31', $settlement->period_end->format('Y-m-d'));
        $this->assertNotNull($settlement->executed_at);
        $this->assertDatabaseHas('transactions', [
            'ledger_id' => $ledger->id,
            'type' => TransactionType::Settlement->value,
            'date' => '2026-03-31',
        ]);

        $settlementTransaction = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', TransactionType::Settlement->value)
            ->first();

        $this->assertNotNull($settlementTransaction);
        $this->assertSame($settlement->id, $settlementTransaction?->settlement_id);
    }

    public function testExecuteIsIdempotentForSamePeriod(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create([
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => AccountType::Personal->value,
        ]);
        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => '2026-01-01',
        ]);

        // Act
        $action = $this->app->make(ExecuteSettlementAction::class);
        $first = $action->execute($ledger, '2026-03-31');
        $second = $action->execute($ledger, '2026-03-31');

        // Assert
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('settlements', 1);
    }
}
