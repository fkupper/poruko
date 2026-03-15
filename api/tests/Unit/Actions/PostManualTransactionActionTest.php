<?php

namespace Tests\Unit\Actions;

use App\Enums\PostingDirection;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(PostManualTransactionAction::class)]
class PostManualTransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function testItCreatesExactlyTwoBalancedPostingsForAnySplitRule(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $creditAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $debitAccount = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        // Act
        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $creditAccount->id,
            'debit_account_id' => $debitAccount->id,
            'amount' => 5000,
            'description' => 'Groceries',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ]));

        // Assert
        $this->assertSame(5000, $transaction->amount);
        $this->assertCount(2, $transaction->postings);

        $credit = $transaction->postings->firstWhere('direction', PostingDirection::Credit);
        $debit = $transaction->postings->firstWhere('direction', PostingDirection::Debit);

        $this->assertSame(5000, $credit->amount);
        $this->assertSame($creditAccount->id, $credit->account_id);
        $this->assertSame(5000, $debit->amount);
        $this->assertSame($debitAccount->id, $debit->account_id);
    }

    public function testItRejectsNonPositiveAmount(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('Amount must be greater than zero.');

        // Act
        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 0,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
        ]));
    }

    #[DataProvider('accountOutsideLedgerDataProvider')]
    public function testItRejectsAccountOutsideTargetLedger(string $foreignAccount): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $otherLedger = Ledger::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $foreignCredit = Account::factory()->create(['ledger_id' => $otherLedger->id]);
        $foreignDebit = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $payload = [
            'ledger_id' => $ledger->id,
            'credit_account_id' => $foreignAccount === 'credit' ? $foreignCredit->id : $credit->id,
            'debit_account_id' => $foreignAccount === 'debit' ? $foreignDebit->id : $debit->id,
            'amount' => 100,
            'description' => "Foreign {$foreignAccount}",
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
        ];

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('do not belong to the target ledger');

        // Act
        $action->execute(PostManualTransactionData::fromArray($payload));
    }

    public static function accountOutsideLedgerDataProvider(): array
    {
        return [
            'credit account outside ledger' => ['credit'],
            'debit account outside ledger' => ['debit'],
        ];
    }

    public function testItStoresParticipantsAsIs(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);
        $participants = [
            ['user_id' => $user->id, 'share' => 70],
        ];

        // Act
        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 1000,
            'description' => 'With participants',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'individual',
            'participants' => $participants,
        ]));

        // Assert
        $this->assertSame($participants, $transaction->participants);
    }

    public function testItValidatesIndividualSplitRequiresExactlyOneParticipant(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('exactly one participant');

        // Act
        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 100,
            'description' => 'Too many',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'individual',
            'participants' => [
                ['user_id' => $userA->id],
                ['user_id' => $userB->id],
            ],
        ]));
    }

    public function testItValidatesManualSplitRequiresShareGreaterThanZero(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('share > 0');

        // Act
        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 100,
            'description' => 'No share',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'manual',
            'participants' => [
                ['user_id' => $user->id],
            ],
        ]));
    }

    public function testItWorksWithEmptyParticipants(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        // Act
        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 500,
            'description' => 'All users default',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
        ]));

        // Assert
        $this->assertSame(500, $transaction->amount);
        $this->assertSame([], $transaction->participants);
        $this->assertCount(2, $transaction->postings);
    }

    public function testItCreatesTwoPostingsForProportionalSplit(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        // Act
        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 1000,
            'description' => 'Proportional kept as metadata',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'proportional',
            'participants' => [['user_id' => $user->id]],
        ]));

        // Assert
        $this->assertCount(2, $transaction->postings);
        $this->assertSame(1000, (int) $transaction->postings->where('direction', PostingDirection::Credit)->sum('amount'));
        $this->assertSame(1000, (int) $transaction->postings->where('direction', PostingDirection::Debit)->sum('amount'));
    }
}
