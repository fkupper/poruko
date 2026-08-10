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
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(PostManualTransactionAction::class)]
class PostManualTransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function testItCreatesBalancedPostingsForAnySplitRule(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $payerAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $action = app(PostManualTransactionAction::class);

        // Act
        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 5000,
            'description' => 'Groceries',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ]));

        // Assert
        $this->assertSame(5000, $transaction->amount);
        $this->assertCount(4, $transaction->postings);

        $payerCredit = $transaction->postings->firstWhere('account_id', $payerAccount->id);
        $expenseDebit = $transaction->postings->firstWhere('account_id', $spaceExpenseAccount->id);

        $this->assertNotNull($payerCredit);
        $this->assertNotNull($expenseDebit);

        $this->assertSame(5000, $payerCredit->amount);
        $this->assertSame(PostingDirection::Credit, $payerCredit->direction);
        $this->assertSame(5000, $expenseDebit->amount);
        $this->assertSame(PostingDirection::Debit, $expenseDebit->direction);
    }

    public function testItRejectsNonPositiveAmount(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $credit = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('Amount must be greater than zero.');

        // Act
        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 0,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
        ]));
    }

    public function testItRejectsAccountOutsideTargetLedger(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $otherLedger = Ledger::factory()->create();
        $foreignCredit = Account::factory()->create(['ledger_id' => $otherLedger->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $payload = [
            'ledger_id' => $ledger->id,
            'payer_account_id' => $foreignCredit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 100,
            'description' => 'Foreign payer',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
        ];

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('One or more accounts do not belong to the target ledger.');

        // Act
        $action->execute(PostManualTransactionData::fromArray($payload));
    }

    public function testItStoresParticipantsAsIs(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Member');
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $action = app(PostManualTransactionAction::class);
        $participants = [
            ['user_id' => $user->id, 'share' => 70],
        ];

        // Act
        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
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
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('exactly one participant');

        // Act
        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
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
        $ledger->users()->attach($user->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Member');
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('share > 0');

        // Act
        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
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

    public function testItValidatesManualSplitRequiresAtLeastOneParticipant(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $credit = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('Manual split requires at least one participant.');

        // Act
        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 100,
            'description' => 'Empty manual participants',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'manual',
            'participants' => [],
        ]));
    }

    public function testItRejectsWhenAllocationsDoNotSumToAmount(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $credit = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $splitService = $this->createMock(\App\Modules\Ledger\Services\TransactionSplitService::class);
        $splitService->method('allocateByRule')->willReturn([$user->id => 50]);
        $this->app->instance(\App\Modules\Ledger\Services\TransactionSplitService::class, $splitService);

        $action = app(PostManualTransactionAction::class);

        // Anticipate
        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('Split allocations must sum to the transaction amount.');

        // Act
        $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 100,
            'description' => 'Broken allocations',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ]));
    }

    public function testItWorksWithEmptyParticipants(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $credit = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $action = app(PostManualTransactionAction::class);

        // Act
        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
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

        $this->assertCount(4, $transaction->postings);
    }

    public function testItCreatesFourPostingsForProportionalSplit(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Member');
        $credit = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $action = app(PostManualTransactionAction::class);

        // Act
        $transaction = $action->execute(PostManualTransactionData::fromArray([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 1000,
            'description' => 'Proportional kept as metadata',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'proportional',
            'participants' => [['user_id' => $user->id]],
        ]));

        // Assert
        $this->assertCount(4, $transaction->postings);
        $this->assertSame(2000, (int) $transaction->postings->where('direction', PostingDirection::Credit)->sum('amount'));
        $this->assertSame(2000, (int) $transaction->postings->where('direction', PostingDirection::Debit)->sum('amount'));
    }
}
