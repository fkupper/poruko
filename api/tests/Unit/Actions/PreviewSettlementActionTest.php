<?php

namespace Tests\Unit\Actions;

use App\Enums\AccountType;
use App\Enums\SettlementMode;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\PreviewSettlementAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(PreviewSettlementAction::class)]
class PreviewSettlementActionTest extends TestCase
{
    use RefreshDatabase;

    public function testMixedSplitRulesInSamePeriodComputesDifferentLiabilities(): void
    {
        // Arrange
        [$ledger, $userA, $userB, $aAccount, $bAccount, $externalAccount] = $this->seedTwoUserLedger(
            incomeA: 100_000,
            incomeB: 100_000,
        );

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 10_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'description' => 'Equal expense',
            'date' => '2026-03-15',
        ]);

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $bAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 9_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Proportional->value,
            'participants' => [],
            'description' => 'Proportional expense',
            'date' => '2026-03-20',
        ]);

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert — equal incomes → proportional behaves like equal
        $breakdownA = $this->breakdownFor($result, $userA->id);
        $breakdownB = $this->breakdownFor($result, $userB->id);

        // Equal 10000: each gets 5000. Proportional 9000 (50/50 income): each gets 4500.
        $this->assertSame(9_500, $breakdownA['target_liability']);
        $this->assertSame(9_500, $breakdownB['target_liability']);
        $this->assertSame(10_000, $breakdownA['paid_out_of_pocket']);
        $this->assertSame(9_000, $breakdownB['paid_out_of_pocket']);
        $this->assertSame(19_000, $result['summary']['total_shared_spend']);
    }

    public function testMixedSplitRulesWithUnequalIncomesComputesProportionalDifferently(): void
    {
        // Arrange — A earns 3x B
        [$ledger, $userA, $userB, $aAccount, $bAccount, $externalAccount] = $this->seedTwoUserLedger(
            incomeA: 300_000,
            incomeB: 100_000,
        );

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 8_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'description' => 'Equal split expense',
            'date' => '2026-03-10',
        ]);

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $bAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 10_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Proportional->value,
            'participants' => [],
            'description' => 'Proportional expense',
            'date' => '2026-03-12',
        ]);

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert
        $breakdownA = $this->breakdownFor($result, $userA->id);
        $breakdownB = $this->breakdownFor($result, $userB->id);

        // Equal 8000: A=4000, B=4000
        // Proportional 10000: A=floor(300k/400k * 10000)=7500, B=10000-7500=2500
        $this->assertSame(4_000 + 7_500, $breakdownA['target_liability']);
        $this->assertSame(4_000 + 2_500, $breakdownB['target_liability']);
    }

    public function testManualSplitWithArbitraryWeightsDistributesCorrectly(): void
    {
        // Arrange
        [$ledger, $userA, $userB, $aAccount, $bAccount, $externalAccount] = $this->seedTwoUserLedger();

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 10_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Manual->value,
            'participants' => [
                ['user_id' => $userA->id, 'share' => 3],
                ['user_id' => $userB->id, 'share' => 7],
            ],
            'description' => 'Manual 30/70 split',
            'date' => '2026-03-15',
        ]);

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert — 3/10 * 10000 = 3000 for A, remainder 7000 for B
        $breakdownA = $this->breakdownFor($result, $userA->id);
        $breakdownB = $this->breakdownFor($result, $userB->id);

        $this->assertSame(3_000, $breakdownA['target_liability']);
        $this->assertSame(7_000, $breakdownB['target_liability']);
    }

    public function testIndividualSplitAssigns100PercentToSoleParticipant(): void
    {
        // Arrange
        [$ledger, $userA, $userB, $aAccount, $bAccount, $externalAccount] = $this->seedTwoUserLedger();

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 5_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Individual->value,
            'participants' => [
                ['user_id' => $userB->id],
            ],
            'description' => 'Individual expense for B',
            'date' => '2026-03-15',
        ]);

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert — B gets 100% liability, A paid out-of-pocket
        $breakdownA = $this->breakdownFor($result, $userA->id);
        $breakdownB = $this->breakdownFor($result, $userB->id);

        $this->assertSame(0, $breakdownA['target_liability']);
        $this->assertSame(5_000, $breakdownB['target_liability']);
        $this->assertSame(5_000, $breakdownA['paid_out_of_pocket']);
        $this->assertSame(0, $breakdownB['paid_out_of_pocket']);
    }

    public function testParticipantSubsetReNormalizesProportionalSplit(): void
    {
        // Arrange — 3 users, only 2 are participants
        $ledger = Ledger::factory()->create([
            'settlement_mode' => 'direct_p2p',
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);

        $userA = User::factory()->create(['name' => 'Alice']);
        $userB = User::factory()->create(['name' => 'Bob']);
        $userC = User::factory()->create(['name' => 'Carol']);
        $ledger->users()->attach($userA->id, ['role' => 'admin']);
        $ledger->users()->attach($userB->id, ['role' => 'member']);
        $ledger->users()->attach($userC->id, ['role' => 'member']);

        $aAccount = $this->seedPersonalAccount($ledger, $userA);
        $bAccount = $this->seedPersonalAccount($ledger, $userB);
        $this->seedPersonalAccount($ledger, $userC);
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::External->value,
        ]);

        $this->seedFinancialProfile($ledger, $userA, 200_000);
        $this->seedFinancialProfile($ledger, $userB, 100_000);
        $this->seedFinancialProfile($ledger, $userC, 300_000);

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 9_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Proportional->value,
            'participants' => [
                ['user_id' => $userA->id],
                ['user_id' => $userB->id],
            ],
            'description' => 'Only A and B participate',
            'date' => '2026-03-15',
        ]);

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert — re-normalized to A(200k) + B(100k) = 300k total
        // A: floor(200k/300k * 9000) = 6000, B: 9000-6000 = 3000
        $breakdownA = $this->breakdownFor($result, $userA->id);
        $breakdownB = $this->breakdownFor($result, $userB->id);
        $breakdownC = $this->breakdownFor($result, $userC->id);

        $this->assertSame(6_000, $breakdownA['target_liability']);
        $this->assertSame(3_000, $breakdownB['target_liability']);
        $this->assertSame(0, $breakdownC['target_liability']);
    }

    public function testOutOfPocketOnlyCountsPersonalAccountPayments(): void
    {
        // Arrange
        [$ledger, $userA, $userB, $aAccount, $bAccount, $externalAccount] = $this->seedTwoUserLedger();

        $poolAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::Pool->value,
        ]);

        // A pays from personal account
        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 6_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'description' => 'Personal payment by A',
            'date' => '2026-03-10',
        ]);

        // Pool pays (should NOT count as out-of-pocket for anyone)
        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $poolAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 4_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'description' => 'Pool payment',
            'date' => '2026-03-12',
        ]);

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert
        $breakdownA = $this->breakdownFor($result, $userA->id);
        $breakdownB = $this->breakdownFor($result, $userB->id);

        $this->assertSame(6_000, $breakdownA['paid_out_of_pocket']);
        $this->assertSame(0, $breakdownB['paid_out_of_pocket']);

        // Liabilities are equal split across both transactions: (6000+4000)/2 = 5000 each
        $this->assertSame(5_000, $breakdownA['target_liability']);
        $this->assertSame(5_000, $breakdownB['target_liability']);

        $this->assertSame(10_000, $result['summary']['total_shared_spend']);
    }

    public function testP2pModeGeneratesPersonToPersonTransfers(): void
    {
        // Arrange
        [$ledger, $userA, $userB, $aAccount, $bAccount, $externalAccount] = $this->seedTwoUserLedger();

        // A pays everything, equal split → B owes A
        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 10_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'description' => 'A pays for both',
            'date' => '2026-03-15',
        ]);

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert — P2P transfer from B's account to A's account
        $this->assertCount(1, $result['required_transfers']);
        $transfer = $result['required_transfers'][0];
        $this->assertSame($bAccount->id, $transfer['from_account_id']);
        $this->assertSame($aAccount->id, $transfer['to_account_id']);
        $this->assertSame(5_000, $transfer['amount']);
    }

    public function testJointClearinghouseModeGeneratesDebtorToPoolAndPoolToCreditorTransfers(): void
    {
        // Arrange — joint clearinghouse: A pays, equal split → B owes A
        $ledger = Ledger::factory()->create([
            'settlement_mode' => SettlementMode::JointClearinghouse->value,
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);

        $userA = User::factory()->create(['name' => 'Alice']);
        $userB = User::factory()->create(['name' => 'Bob']);
        $ledger->users()->attach($userA->id, ['role' => 'admin']);
        $ledger->users()->attach($userB->id, ['role' => 'member']);

        $aAccount = $this->seedPersonalAccount($ledger, $userA);
        $bAccount = $this->seedPersonalAccount($ledger, $userB);
        $poolAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::Pool->value,
        ]);
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::External->value,
        ]);

        $this->seedFinancialProfile($ledger, $userA, 100_000);
        $this->seedFinancialProfile($ledger, $userB, 100_000);

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 10_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'description' => 'A pays for both',
            'date' => '2026-03-15',
        ]);

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert — debtor (B) pays to pool, pool pays to creditor (A)
        $transfers = $result['required_transfers'];
        $this->assertCount(2, $transfers, 'Joint clearinghouse should have debtor→pool and pool→creditor transfers');

        $debtorToPool = collect($transfers)->first(
            fn (array $t) => $t['from_account_id'] === $bAccount->id && $t['to_account_id'] === $poolAccount->id,
        );
        $poolToCreditor = collect($transfers)->first(
            fn (array $t) => $t['from_account_id'] === $poolAccount->id && $t['to_account_id'] === $aAccount->id,
        );

        $this->assertNotNull($debtorToPool, 'Debtor (B) should transfer to pool');
        $this->assertSame(5_000, $debtorToPool['amount']);

        $this->assertNotNull($poolToCreditor, 'Pool should transfer to creditor (A)');
        $this->assertSame(5_000, $poolToCreditor['amount']);
    }

    public function testEqualSplitDistributesRemainderCorrectly(): void
    {
        // Arrange — 10001 cents between 2 users → 5001 + 5000
        [$ledger, $userA, $userB, $aAccount, $bAccount, $externalAccount] = $this->seedTwoUserLedger();

        Transaction::query()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $aAccount->id,
            'debit_account_id' => $externalAccount->id,
            'amount' => 10_001,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'description' => 'Odd cent expense',
            'date' => '2026-03-15',
        ]);

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert — first participant gets the extra cent
        $breakdownA = $this->breakdownFor($result, $userA->id);
        $breakdownB = $this->breakdownFor($result, $userB->id);

        $this->assertSame(10_001, $breakdownA['target_liability'] + $breakdownB['target_liability']);
    }

    public function testResponseShapeIncludesAllRequiredFields(): void
    {
        // Arrange
        [$ledger] = $this->seedTwoUserLedger();

        // Act
        $result = $this->runPreview($ledger, '2026-03-31');

        // Assert
        $this->assertArrayHasKey('ledger_id', $result);
        $this->assertArrayHasKey('period_start', $result);
        $this->assertArrayHasKey('period_end', $result);
        $this->assertArrayHasKey('settlement_mode', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('total_shared_spend', $result['summary']);
        $this->assertArrayHasKey('pool_current_balance', $result['summary']);
        $this->assertArrayHasKey('user_breakdowns', $result);
        $this->assertArrayHasKey('required_transfers', $result);

        foreach ($result['user_breakdowns'] as $breakdown) {
            $this->assertArrayHasKey('user_id', $breakdown);
            $this->assertArrayHasKey('name', $breakdown);
            $this->assertArrayHasKey('active_ratio', $breakdown);
            $this->assertArrayHasKey('target_liability', $breakdown);
            $this->assertArrayHasKey('paid_out_of_pocket', $breakdown);
            $this->assertArrayHasKey('net_balance', $breakdown);
        }
    }

    /*
     * Seeders.
     */

    /**
     * @return array{Ledger, User, User, Account, Account, Account}
     */
    private function seedTwoUserLedger(int $incomeA = 100_000, int $incomeB = 100_000): array
    {
        $ledger = Ledger::factory()->create([
            'settlement_mode' => 'direct_p2p',
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);

        $userA = User::factory()->create(['name' => 'Alice']);
        $userB = User::factory()->create(['name' => 'Bob']);
        $ledger->users()->attach($userA->id, ['role' => 'admin']);
        $ledger->users()->attach($userB->id, ['role' => 'member']);

        $aAccount = $this->seedPersonalAccount($ledger, $userA);
        $bAccount = $this->seedPersonalAccount($ledger, $userB);
        $externalAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::External->value,
        ]);

        $this->seedFinancialProfile($ledger, $userA, $incomeA);
        $this->seedFinancialProfile($ledger, $userB, $incomeB);

        return [$ledger, $userA, $userB, $aAccount, $bAccount, $externalAccount];
    }

    private function seedPersonalAccount(Ledger $ledger, User $user): Account
    {
        $mainId = DB::table('ledger_user')
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->value('main_personal_account_id');

        return Account::query()->findOrFail($mainId);
    }

    private function seedFinancialProfile(Ledger $ledger, User $user, int $income): FinancialProfile
    {
        return FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => '2026-01-01',
            'incomes' => [['description' => 'Salary', 'amount' => $income]],
            'deductions' => [],
        ]);
    }

    /*
     * Helpers.
     */

    /**
     * @return array<string, mixed>
     */
    private function runPreview(Ledger $ledger, string $periodEnd): array
    {
        /** @var PreviewSettlementAction $action */
        $action = $this->app->make(PreviewSettlementAction::class);

        return $action->executeForPeriodEnd($ledger, $periodEnd);
    }

    /**
     * @param array<string, mixed> $result
     * @return array{user_id: int, name: string, active_ratio: float, target_liability: int, paid_out_of_pocket: int, net_balance: int}
     */
    private function breakdownFor(array $result, int $userId): array
    {
        foreach ($result['user_breakdowns'] as $breakdown) {
            if ($breakdown['user_id'] === $userId) {
                return $breakdown;
            }
        }

        $this->fail("No breakdown found for user_id={$userId}");
    }
}
