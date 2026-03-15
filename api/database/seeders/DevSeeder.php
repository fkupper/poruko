<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\SettlementMode;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Models\User;
use App\Modules\Ledger\Actions\ExecuteSettlementAction;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use Illuminate\Database\Seeder;

/**
 * Joint Clearinghouse scenario: Bob and Clara share expenses. The settlement
 * engine calculates liabilities by split rule (proportional 60/40, equal 50/50),
 * subtracts out-of-pocket payments, and issues transfers to refill the House
 * Joint Account.
 *
 * Scenario: Rent $1000 + Holiday $100 + Groceries $200 (all proportional);
 * Restaurant $100 (equal). Bob paid Groceries and Restaurant ($300). Result:
 * Bob owes $530, Clara owes $570. Settlement transfers refill the pool.
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $bob = User::query()->updateOrCreate(
            ['email' => 'bob@example.com'],
            [
                'name' => 'Bob',
                'password' => 'password',
            ],
        );

        $clara = User::query()->updateOrCreate(
            ['email' => 'clara@example.com'],
            [
                'name' => 'Clara',
                'password' => 'password',
            ],
        );

        $ledger = $bob->ledgers()->first();

        if (!$ledger) {
            $ledger = Ledger::query()->create([
                'name' => 'Dev Ledger',
                'settlement_mode' => SettlementMode::JointClearinghouse->value,
                'settlement_timezone' => 'UTC',
                'settlement_cutoff_day' => 1,
                'settlement_cutoff_time' => '00:00:00',
                'settlement_auto_execute_enabled' => false,
            ]);
            $ledger->users()->attach($bob->id, ['role' => 'admin']);
        }

        if (!$ledger->users()->whereKey($clara->id)->exists()) {
            $ledger->users()->attach($clara->id, ['role' => 'member']);
        }

        $housePool = Account::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'code' => 'DEV-HOUSE-POOL',
            ],
            [
                'owner_id' => null,
                'type' => AccountType::Pool,
                'name' => 'House Joint Account',
                'base_budget' => 200000,
            ],
        );

        $bobWallet = Account::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'code' => 'DEV-BOB-WALLET',
            ],
            [
                'owner_id' => $bob->id,
                'type' => AccountType::Personal,
                'name' => "Bob's Personal Account",
            ],
        );

        $claraWallet = Account::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'code' => 'DEV-CLARA-WALLET',
            ],
            [
                'owner_id' => $clara->id,
                'type' => AccountType::Personal,
                'name' => "Clara's Personal Account",
            ],
        );

        $generalExpenses = Account::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'code' => 'DEV-GENERAL-EXP',
            ],
            [
                'owner_id' => null,
                'type' => AccountType::External,
                'name' => 'General Expenses Account',
            ],
        );

        $holidayExpenses = Account::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'code' => 'DEV-HOLIDAY-EXP',
            ],
            [
                'owner_id' => null,
                'type' => AccountType::External,
                'name' => 'Holiday Expenses Account',
            ],
        );

        $profileStart = now()->subMonthsNoOverflow(3)->startOfMonth()->format('Y-m-d');

        FinancialProfile::query()->updateOrCreate(
            [
                'ledger_id' => $ledger->id,
                'user_id' => $bob->id,
                'valid_from' => $profileStart,
            ],
            [
                'valid_to' => null,
                'incomes' => [['description' => 'Salary', 'amount' => 600000]],
                'deductions' => [],
            ],
        );

        FinancialProfile::query()->updateOrCreate(
            [
                'ledger_id' => $ledger->id,
                'user_id' => $clara->id,
                'valid_from' => $profileStart,
            ],
            [
                'valid_to' => null,
                'incomes' => [['description' => 'Salary', 'amount' => 400000]],
                'deductions' => [],
            ],
        );

        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();
        $lastMonthStart = $lastMonthEnd->copy()->startOfMonth();
        $postAction = app(PostManualTransactionAction::class);
        $participants = [['user_id' => $bob->id], ['user_id' => $clara->id]];

        if ($ledger->transactions()->where('type', '!=', 'settlement')->count() === 0) {
            $day5 = $lastMonthStart->copy()->addDays(5)->format('Y-m-d');
            $day10 = $lastMonthStart->copy()->addDays(10)->format('Y-m-d');
            $day15 = $lastMonthStart->copy()->addDays(15)->format('Y-m-d');
            $day20 = $lastMonthStart->copy()->addDays(20)->format('Y-m-d');

            $postAction->execute(PostManualTransactionData::fromArray([
                'ledger_id' => $ledger->id,
                'credit_account_id' => $housePool->id,
                'debit_account_id' => $generalExpenses->id,
                'amount' => 100000,
                'split_rule' => TransactionSplitRule::Proportional->value,
                'participants' => $participants,
                'description' => 'Rent',
                'date' => $day5,
                'type' => TransactionType::Manual->value,
            ]));

            $postAction->execute(PostManualTransactionData::fromArray([
                'ledger_id' => $ledger->id,
                'credit_account_id' => $housePool->id,
                'debit_account_id' => $holidayExpenses->id,
                'amount' => 10000,
                'split_rule' => TransactionSplitRule::Proportional->value,
                'participants' => $participants,
                'description' => 'Holiday Expense',
                'date' => $day10,
                'type' => TransactionType::Manual->value,
            ]));

            $postAction->execute(PostManualTransactionData::fromArray([
                'ledger_id' => $ledger->id,
                'credit_account_id' => $bobWallet->id,
                'debit_account_id' => $generalExpenses->id,
                'amount' => 20000,
                'split_rule' => TransactionSplitRule::Proportional->value,
                'participants' => $participants,
                'description' => 'Groceries',
                'date' => $day15,
                'type' => TransactionType::Manual->value,
            ]));

            $postAction->execute(PostManualTransactionData::fromArray([
                'ledger_id' => $ledger->id,
                'credit_account_id' => $bobWallet->id,
                'debit_account_id' => $generalExpenses->id,
                'amount' => 10000,
                'split_rule' => TransactionSplitRule::Equal->value,
                'participants' => $participants,
                'description' => 'Restaurant',
                'date' => $day20,
                'type' => TransactionType::Manual->value,
            ]));
        }

        $periodEnd = $lastMonthEnd->format('Y-m-d');
        $periodStart = $lastMonthStart->format('Y-m-d');
        $leaveSettlementPending = filter_var(
            config('dev.seed_settlement_pending', false),
            FILTER_VALIDATE_BOOLEAN,
        );

        if ($leaveSettlementPending) {
            Settlement::query()->firstOrCreate(
                [
                    'ledger_id' => $ledger->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ],
                ['executed_at' => null],
            );
        } else {
            $settlement = app(ExecuteSettlementAction::class)->execute($ledger, $periodEnd);

            if ($settlement->executed_at === null) {
                $settlement->update([
                    'executed_at' => $lastMonthEnd->copy()->setTime(12, 0)->toDateTimeString(),
                ]);
            }
        }
    }
}
