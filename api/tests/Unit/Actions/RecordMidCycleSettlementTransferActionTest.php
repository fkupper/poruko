<?php

namespace Tests\Unit\Actions;

use App\Enums\AccountType;
use App\Enums\PostingDirection;
use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\ExecuteSettlementAction;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Actions\PreviewSettlementAction;
use App\Modules\Ledger\Actions\RecordMidCycleSettlementTransferAction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('settlements')]
#[CoversClass(RecordMidCycleSettlementTransferAction::class)]
class RecordMidCycleSettlementTransferActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-19 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_records_partial_transfer_and_preserves_final_settlement_math(): void
    {
        [$ledger, $aliceAccount, $bobLiabilityAccount] = $this->seedPendingTransfer();

        $transaction = $this->action()->execute(
            $ledger,
            '2026-09-30',
            $bobLiabilityAccount->id,
            $aliceAccount->id,
            2_000,
            '0f4a9dc1-d6a6-4f09-a8fd-14be65e0e21c',
        );

        $this->assertSame(TransactionType::Settlement, $transaction->type);
        $this->assertSame(TransactionSource::System, $transaction->source);
        $this->assertSame($aliceAccount->id, $transaction->destination_account_id);
        $this->assertSame('2026-09-19', $transaction->date->toDateString());
        $this->assertSame('mid_cycle_settlement_transfer', $transaction->source_metadata['operation']);
        $this->assertSame('2026-09-30', $transaction->source_metadata['period_end']);
        $this->assertNotNull($transaction->settlement_id);
        $this->assertNull($transaction->settlement?->executed_at);

        $this->assertDatabaseHas('postings', [
            'transaction_id' => $transaction->id,
            'account_id' => $bobLiabilityAccount->id,
            'direction' => PostingDirection::Credit->value,
            'amount' => 2_000,
        ]);
        $this->assertDatabaseHas('postings', [
            'transaction_id' => $transaction->id,
            'account_id' => $aliceAccount->id,
            'direction' => PostingDirection::Debit->value,
            'amount' => 2_000,
        ]);

        $preview = app(PreviewSettlementAction::class)->executeForPeriodEnd($ledger, '2026-09-30');
        $this->assertCount(1, $preview['required_transfers']);
        $this->assertSame(3_000, $preview['required_transfers'][0]['amount']);

        $settlement = app(ExecuteSettlementAction::class)->execute($ledger, '2026-09-30');

        $this->assertNotNull($settlement->executed_at);
        $this->assertSame(
            5_000,
            (int) Transaction::query()
                ->where('settlement_id', $settlement->id)
                ->where('type', TransactionType::Settlement->value)
                ->sum('amount'),
        );

        $settledPreview = app(PreviewSettlementAction::class)->executeForPeriodEnd($ledger, '2026-09-30');
        $this->assertSame([], $settledPreview['required_transfers']);
    }

    public function test_returns_the_original_transaction_for_an_idempotent_retry(): void
    {
        [$ledger, $aliceAccount, $bobLiabilityAccount] = $this->seedPendingTransfer();
        $key = 'd2391384-b599-44c2-a1dc-63f31a2805bd';

        $first = $this->action()->execute(
            $ledger,
            '2026-09-30',
            $bobLiabilityAccount->id,
            $aliceAccount->id,
            5_000,
            $key,
        );
        $second = $this->action()->execute(
            $ledger,
            '2026-09-30',
            $bobLiabilityAccount->id,
            $aliceAccount->id,
            5_000,
            $key,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            Transaction::query()->where('settlement_transfer_key', $key)->count(),
        );
        $this->assertCount(2, $second->postings);
    }

    public function test_rejects_an_amount_above_the_remaining_suggestion(): void
    {
        [$ledger, $aliceAccount, $bobLiabilityAccount] = $this->seedPendingTransfer();

        try {
            $this->action()->execute(
                $ledger,
                '2026-09-30',
                $bobLiabilityAccount->id,
                $aliceAccount->id,
                5_001,
                'f08be4ef-6fd3-438d-bd45-37a70eb70161',
            );
            $this->fail('Expected an overpayment validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertDatabaseMissing('transactions', [
            'ledger_id' => $ledger->id,
            'type' => TransactionType::Settlement->value,
        ]);
        $this->assertDatabaseMissing('settlements', [
            'ledger_id' => $ledger->id,
            'period_end' => '2026-09-30',
        ]);
    }

    public function test_rejects_a_non_positive_amount_when_called_directly(): void
    {
        [$ledger, $aliceAccount, $bobLiabilityAccount] = $this->seedPendingTransfer();

        try {
            $this->action()->execute(
                $ledger,
                '2026-09-30',
                $bobLiabilityAccount->id,
                $aliceAccount->id,
                0,
                'a24b89b2-b214-445c-bdfb-a0799bf70a2a',
            );
            $this->fail('Expected a non-positive amount validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertDatabaseMissing('transactions', [
            'ledger_id' => $ledger->id,
            'type' => TransactionType::Settlement->value,
        ]);
    }

    public function test_rejects_reusing_a_key_for_different_transfer_data(): void
    {
        [$ledger, $aliceAccount, $bobLiabilityAccount] = $this->seedPendingTransfer();
        $key = '19fd2de9-90db-4791-a765-8eff7ac8acbb';

        $this->action()->execute(
            $ledger,
            '2026-09-30',
            $bobLiabilityAccount->id,
            $aliceAccount->id,
            2_000,
            $key,
        );

        $this->expectException(ValidationException::class);

        $this->action()->execute(
            $ledger,
            '2026-09-30',
            $bobLiabilityAccount->id,
            $aliceAccount->id,
            1_000,
            $key,
        );
    }

    public function test_rejects_accounts_outside_the_current_suggestion(): void
    {
        [$ledger, $aliceAccount] = $this->seedPendingTransfer();
        $foreignAccount = Account::factory()->create();

        try {
            $this->action()->execute(
                $ledger,
                '2026-09-30',
                $foreignAccount->id,
                $aliceAccount->id,
                1_000,
                '0fc758a3-509c-4658-b8fd-7d887bb7f1f8',
            );
            $this->fail('Expected an invalid transfer validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('from_account_id', $exception->errors());
        }
    }

    public function test_rejects_transfers_for_an_executed_settlement(): void
    {
        [$ledger, $aliceAccount, $bobLiabilityAccount] = $this->seedPendingTransfer();
        Settlement::factory()->create([
            'ledger_id' => $ledger->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'executed_at' => '2026-10-01 00:00:00',
        ]);

        try {
            $this->action()->execute(
                $ledger,
                '2026-09-30',
                $bobLiabilityAccount->id,
                $aliceAccount->id,
                5_000,
                '49538574-48e9-4bba-80f0-95769db1137c',
            );
            $this->fail('Expected a settled-cycle validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period_end', $exception->errors());
        }
    }

    private function action(): RecordMidCycleSettlementTransferAction
    {
        return app(RecordMidCycleSettlementTransferAction::class);
    }

    /**
     * @return array{Ledger, Account, Account}
     */
    private function seedPendingTransfer(): array
    {
        $ledger = Ledger::factory()->create([
            'settlement_mode' => 'direct_p2p',
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 30,
            'settlement_cutoff_time' => '23:59:00',
        ]);
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $ledger->users()->attach($alice->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $alice->assignRole('Admin');
        $ledger->users()->attach($bob->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $bob->assignRole('Member');

        $aliceAccount = $this->mainAccount($ledger, $alice);
        $bobLiabilityAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('owner_id', $bob->id)
            ->where('type', AccountType::UserLiability->value)
            ->firstOrFail();
        $expenseAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::SpaceExpense->value,
        ]);

        foreach ([$alice, $bob] as $user) {
            FinancialProfile::factory()->create([
                'ledger_id' => $ledger->id,
                'user_id' => $user->id,
                'valid_from' => '2026-01-01',
                'incomes' => [['description' => 'Income', 'amount' => 100_000]],
                'deductions' => [],
            ]);
        }

        app(PostManualTransactionAction::class)->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $aliceAccount->id,
            'destination_account_id' => $expenseAccount->id,
            'amount' => 10_000,
            'type' => TransactionType::Manual->value,
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'description' => 'Shared groceries',
            'date' => '2026-09-10',
        ]));

        return [$ledger, $aliceAccount, $bobLiabilityAccount];
    }

    private function mainAccount(Ledger $ledger, User $user): Account
    {
        $mainAccountId = DB::table('ledger_user')
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->value('main_personal_account_id');

        return Account::query()->findOrFail($mainAccountId);
    }
}
