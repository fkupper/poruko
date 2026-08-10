<?php

namespace Tests\Unit\Actions;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Models\User;
use App\Modules\Ledger\Actions\ConfirmSettlementAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[Group('settlements')]
#[CoversClass(ConfirmSettlementAction::class)]
class ConfirmSettlementActionTest extends TestCase
{
    use RefreshDatabase;

    public function testExecuteCreatesSettlementAndExecutesWhenSafetyGateBlocksAuto(): void
    {
        // Arrange
        [$ledger, $admin, $adminAccount, $memberAccount, $externalAccount] = $this->seedLedgerWithExpenses();

        $action = $this->app->make(ConfirmSettlementAction::class);

        // Act
        $settlement = $action->execute($ledger, '2026-03-31');

        // Assert
        $this->assertInstanceOf(Settlement::class, $settlement);
        $this->assertSame($ledger->id, $settlement->ledger_id);
        $this->assertSame('2026-03-31', $settlement->period_end->format('Y-m-d'));
        $this->assertNotNull($settlement->executed_at);

        $this->assertDatabaseHas('transactions', [
            'ledger_id' => $ledger->id,
            'type' => TransactionType::Settlement->value,
            'date' => '2026-03-31',
        ]);
    }

    public function testExecuteThrowsWhenCycleDoesNotRequireManualConfirmation(): void
    {
        // Arrange — auto_execute_enabled = true, no transfers → safety gate allows auto
        [$ledger] = $this->seedLedgerWithExpenses([
            'settlement_auto_execute_enabled' => true,
        ]);

        $action = $this->app->make(ConfirmSettlementAction::class);

        // Anticipate
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This cycle does not require manual confirmation.');

        // Act
        $action->execute($ledger, '2026-03-31');
    }

    /*
     * Seeders.
     */

    /**
     * @param array<string, mixed> $ledgerOverrides
     * @return array{Ledger, User, Account, Account, Account}
     */
    private function seedLedgerWithExpenses(array $ledgerOverrides = []): array
    {
        $admin = User::factory()->create(['name' => 'Admin']);
        $member = User::factory()->create(['name' => 'Member']);
        $ledger = Ledger::factory()->create(array_merge([
            'settlement_mode' => 'direct_p2p',
            'settlement_auto_execute_enabled' => false,
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ], $ledgerOverrides));
        $ledger->users()->attach($admin->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $admin->assignRole('Admin');
        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');

        $adminAccount = Account::query()->findOrFail(
            DB::table('ledger_user')->where('ledger_id', $ledger->id)->where('user_id', $admin->id)->value('main_personal_account_id'),
        );
        $memberAccount = Account::query()->findOrFail(
            DB::table('ledger_user')->where('ledger_id', $ledger->id)->where('user_id', $member->id)->value('main_personal_account_id'),
        );
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::SpaceExpense->value,
        ]);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $admin->id,
            'valid_from' => '2026-01-01',
            'incomes' => [['description' => 'Income', 'amount' => 100000]],
            'deductions' => [],
        ]);
        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $member->id,
            'valid_from' => '2026-01-01',
            'incomes' => [['description' => 'Income', 'amount' => 100000]],
            'deductions' => [],
        ]);

        app(\App\Modules\Ledger\Actions\PostManualTransactionAction::class)->execute(\App\Modules\Ledger\Data\PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $adminAccount->id,
            'destination_account_id' => $externalAccount->id,
            'amount' => 10000,
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
            'description' => 'Shared expense',
            'date' => '2026-03-15',
        ]));

        return [$ledger, $admin, $adminAccount, $memberAccount, $externalAccount];
    }
}
