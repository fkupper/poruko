<?php

namespace Tests\Unit\Actions;

use App\Enums\PostingDirection;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(PostManualTransactionAction::class)]
class PostManualTransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_balanced_postings_for_equal_split_with_remainder(): void
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $payer = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
        ]);
        $participantA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantB = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantC = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Dinner',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [
                ['account_id' => $participantA->id],
                ['account_id' => $participantB->id],
                ['account_id' => $participantC->id],
            ],
        ]));

        $this->assertSame(100, $transaction->amount);
        $this->assertCount(4, $transaction->postings);
        $this->assertSame(100, (int) $transaction->postings->where('direction', PostingDirection::Credit)->sum('amount'));
        $this->assertSame(100, (int) $transaction->postings->where('direction', PostingDirection::Debit)->sum('amount'));
        $this->assertSame(
            [33, 33, 34],
            $transaction->postings
                ->where('direction', PostingDirection::Debit)
                ->pluck('amount')
                ->sort()
                ->values()
                ->all(),
        );
    }

    public function test_it_rejects_individual_split_when_amounts_do_not_sum_to_total(): void
    {
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantB = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('must equal transaction amount');

        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Groceries',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'individual',
            'participants' => [
                ['account_id' => $participantA->id, 'amount' => 30],
                ['account_id' => $participantB->id, 'amount' => 60],
            ],
        ]));
    }

    public function test_it_rejects_accounts_outside_the_target_ledger(): void
    {
        $ledger = Ledger::factory()->create();
        $otherLedger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $foreignAccount = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('do not belong to the target ledger');

        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Subscription',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [
                ['account_id' => $foreignAccount->id],
            ],
        ]));
    }

    public function test_it_rejects_non_positive_amount(): void
    {
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('Amount must be greater than zero.');

        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 0,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [
                ['account_id' => $participant->id],
            ],
        ]));
    }

    public function test_it_rejects_empty_participants(): void
    {
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('At least one participant is required.');

        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
        ]));
    }

    public function test_it_rejects_unsupported_split_rule(): void
    {
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('Unsupported split rule');

        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'unsupported',
            'participants' => [
                ['account_id' => $participant->id],
            ],
        ]));
    }

    public function test_it_rejects_individual_split_without_amount_per_participant(): void
    {
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('non-negative amount per participant');

        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'individual',
            'participants' => [
                ['account_id' => $participant->id],
            ],
        ]));
    }

    public function test_it_creates_balanced_postings_for_individual_split(): void
    {
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantB = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Groceries',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'individual',
            'participants' => [
                ['account_id' => $participantA->id, 'amount' => 40],
                ['account_id' => $participantB->id, 'amount' => 60],
            ],
        ]));

        $this->assertSame(100, (int) $transaction->postings->where('direction', PostingDirection::Credit)->sum('amount'));
        $this->assertSame(100, (int) $transaction->postings->where('direction', PostingDirection::Debit)->sum('amount'));
    }

    public function test_it_creates_balanced_postings_for_proportional_split(): void
    {
        $ledger = Ledger::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $ledger->users()->attach($userA->id, ['role' => 'admin']);
        $ledger->users()->attach($userB->id, ['role' => 'member']);

        $payer = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userA->id]);
        $accountA = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userA->id]);
        $accountB = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userB->id]);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $userA->id,
            'valid_from' => '2026-03-01',
            'incomes' => [['description' => 'Salary', 'amount' => 300000]],
            'deductions' => [],
        ]);
        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $userB->id,
            'valid_from' => '2026-03-01',
            'incomes' => [['description' => 'Salary', 'amount' => 100000]],
            'deductions' => [],
        ]);

        $action = app(PostManualTransactionAction::class);

        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'description' => 'Rent proportional',
            'date' => '2026-03-12',
            'type' => 'manual',
            'split_rule' => 'proportional',
            'participants' => [
                ['account_id' => $accountA->id],
                ['account_id' => $accountB->id],
            ],
        ]));

        $debits = $transaction->postings->where('direction', PostingDirection::Debit);
        $credits = $transaction->postings->where('direction', PostingDirection::Credit);

        $this->assertSame(1000, (int) $credits->sum('amount'));
        $this->assertSame(1000, (int) $debits->sum('amount'));
        $this->assertCount(2, $debits);

        $debitA = $debits->firstWhere('account_id', $accountA->id);
        $debitB = $debits->firstWhere('account_id', $accountB->id);

        $this->assertSame(750, $debitA->amount);
        $this->assertSame(250, $debitB->amount);
    }

    public function test_proportional_split_handles_remainder_cents_deterministically(): void
    {
        $ledger = Ledger::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $payer = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userA->id]);
        $accountA = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userA->id]);
        $accountB = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userB->id]);
        $accountC = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userC->id]);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $userA->id,
            'valid_from' => '2026-03-01',
            'incomes' => [['description' => 'Salary', 'amount' => 100000]],
            'deductions' => [],
        ]);
        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $userB->id,
            'valid_from' => '2026-03-01',
            'incomes' => [['description' => 'Salary', 'amount' => 100000]],
            'deductions' => [],
        ]);
        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $userC->id,
            'valid_from' => '2026-03-01',
            'incomes' => [['description' => 'Salary', 'amount' => 100000]],
            'deductions' => [],
        ]);

        $action = app(PostManualTransactionAction::class);

        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Test remainder',
            'date' => '2026-03-12',
            'type' => 'manual',
            'split_rule' => 'proportional',
            'participants' => [
                ['account_id' => $accountA->id],
                ['account_id' => $accountB->id],
                ['account_id' => $accountC->id],
            ],
        ]));

        $debits = $transaction->postings->where('direction', PostingDirection::Debit);

        $this->assertSame(100, (int) $debits->sum('amount'));
        $this->assertSame(
            [33, 33, 34],
            $debits->pluck('amount')->sort()->values()->all(),
        );
    }

    public function test_proportional_split_fails_when_no_shareable_income(): void
    {
        $ledger = Ledger::factory()->create();
        $userA = User::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userA->id]);
        $accountA = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userA->id]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('shareable income');

        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'description' => 'No profile',
            'date' => '2026-03-12',
            'type' => 'manual',
            'split_rule' => 'proportional',
            'participants' => [
                ['account_id' => $accountA->id],
            ],
        ]));
    }

    public function test_proportional_split_fails_for_unowned_account(): void
    {
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $pool = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
        ]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('owner');

        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'description' => 'Pool has no owner',
            'date' => '2026-03-12',
            'type' => 'manual',
            'split_rule' => 'proportional',
            'participants' => [
                ['account_id' => $pool->id],
            ],
        ]));
    }
}
