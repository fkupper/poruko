<?php

namespace Tests\Unit\Actions;

use App\Enums\AccountType;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\ExecuteSettlementAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        setPermissionsTeamId($ledger->id);
        $a->assignRole('Admin');
        $ledger->users()->attach($b->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $b->assignRole('Member');

        $aAccount = Account::query()->findOrFail(
            DB::table('ledger_user')->where('ledger_id', $ledger->id)->where('user_id', $a->id)->value('main_personal_account_id'),
        );
        $bAccount = Account::query()->findOrFail(
            DB::table('ledger_user')->where('ledger_id', $ledger->id)->where('user_id', $b->id)->value('main_personal_account_id'),
        );
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::SpaceExpense->value,
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

        app(\App\Modules\Ledger\Actions\PostManualTransactionAction::class)->execute(\App\Modules\Ledger\Data\PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $aAccount->id,
            'destination_account_id' => $externalAccount->id,
            'amount' => 10000,
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
            'description' => 'Shared expense',
            'date' => '2026-03-20',
        ]));

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
        $this->assertSame(TransactionSource::System, $settlementTransaction?->source);
        $this->assertSame('settlement', $settlementTransaction?->source_metadata['operation']);
        $this->assertSame('2026-03-31', $settlementTransaction?->source_metadata['period_end']);
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
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
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

    public function testCannotSettleLaterPeriodWhileEarlierMonthHasUnsettledTransaction(): void
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
        setPermissionsTeamId($ledger->id);
        $a->assignRole('Admin');
        $ledger->users()->attach($b->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $b->assignRole('Member');

        $aAccount = Account::query()->findOrFail(
            DB::table('ledger_user')->where('ledger_id', $ledger->id)->where('user_id', $a->id)->value('main_personal_account_id'),
        );
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::SpaceExpense->value,
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

        app(\App\Modules\Ledger\Actions\PostManualTransactionAction::class)->execute(\App\Modules\Ledger\Data\PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $aAccount->id,
            'destination_account_id' => $externalAccount->id,
            'amount' => 10000,
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
            'description' => 'March expense',
            'date' => '2026-03-20',
        ]));

        $action = $this->app->make(ExecuteSettlementAction::class);

        // Anticipate
        $this->expectException(\App\Modules\Ledger\Exceptions\CannotSettlePeriodWithEarlierOpenPeriodsException::class);
        $this->expectExceptionMessage('Earlier open periods must be settled first.');

        // Act
        $action->execute($ledger, '2026-04-30');
    }

    public function testLockingDoesNotAttachPriorMonthUnsettledTransactionsToLaterSettlement(): void
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
        setPermissionsTeamId($ledger->id);
        $a->assignRole('Admin');
        $ledger->users()->attach($b->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $b->assignRole('Member');

        $aAccount = Account::query()->findOrFail(
            DB::table('ledger_user')->where('ledger_id', $ledger->id)->where('user_id', $a->id)->value('main_personal_account_id'),
        );
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::SpaceExpense->value,
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

        $marchTx = app(\App\Modules\Ledger\Actions\PostManualTransactionAction::class)->execute(\App\Modules\Ledger\Data\PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $aAccount->id,
            'destination_account_id' => $externalAccount->id,
            'amount' => 10000,
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
            'description' => 'March expense',
            'date' => '2026-03-20',
        ]));

        $aprilTx = app(\App\Modules\Ledger\Actions\PostManualTransactionAction::class)->execute(\App\Modules\Ledger\Data\PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $aAccount->id,
            'destination_account_id' => $externalAccount->id,
            'amount' => 4000,
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
            'description' => 'April expense',
            'date' => '2026-04-10',
        ]));

        // Act
        $action = $this->app->make(ExecuteSettlementAction::class);
        $marchSettlement = $action->execute($ledger, '2026-03-31');

        // Assert
        $this->assertSame($marchSettlement->id, $marchTx->fresh()->settlement_id);
        $this->assertNull($aprilTx->fresh()->settlement_id);

        $aprilSettlement = $action->execute($ledger, '2026-04-30');

        $this->assertSame($aprilSettlement->id, $aprilTx->fresh()->settlement_id);
        $this->assertSame($marchSettlement->id, $marchTx->fresh()->settlement_id);
        $this->assertNotSame($marchSettlement->id, $aprilSettlement->id);
    }
}
