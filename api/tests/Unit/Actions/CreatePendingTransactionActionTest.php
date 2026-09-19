<?php

namespace Tests\Unit\Actions;

use App\Enums\AccountType;
use App\Enums\PendingTransactionStatus;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\CreatePendingTransactionAction;
use App\Modules\Ledger\Data\CreatePendingTransactionData;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('pending-transactions')]
#[CoversClass(CreatePendingTransactionAction::class)]
class CreatePendingTransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function testItCreatesProposalWithoutPostingToLedger(): void
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'member']);
        $payerAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
        ]);
        $destinationAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', AccountType::SpaceExpense->value)
            ->firstOrFail();

        $pendingTransaction = app(CreatePendingTransactionAction::class)->execute(
            CreatePendingTransactionData::fromArray([
                'ledger_id' => $ledger->id,
                'user_id' => $user->id,
                'payer_account_id' => $payerAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'raw_data' => ['description' => 'MARKET'],
                'description' => 'Groceries',
                'amount' => 2500,
                'split_rule' => 'equal',
                'participants' => [['user_id' => $user->id]],
                'date' => '2026-09-19',
                'source' => TransactionSource::AiImport->value,
                'confidence' => 0.95,
                'rationale' => 'Merchant match',
            ]),
        );

        $this->assertSame(PendingTransactionStatus::Pending, $pendingTransaction->status);
        $this->assertSame(TransactionSource::AiImport, $pendingTransaction->source);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('postings', 0);
    }

    public function testItRejectsProposerOutsideLedger(): void
    {
        $ledger = Ledger::factory()->create();
        $nonMember = User::factory()->create();

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('active member');

        app(CreatePendingTransactionAction::class)->execute(
            CreatePendingTransactionData::fromArray([
                'ledger_id' => $ledger->id,
                'user_id' => $nonMember->id,
                'raw_data' => [],
                'source' => TransactionSource::Mcp->value,
            ]),
        );
    }

    public function testItRejectsSuggestedAccountOutsideLedger(): void
    {
        $ledger = Ledger::factory()->create();
        $otherLedger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'member']);
        $foreignAccount = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('suggested accounts');

        app(CreatePendingTransactionAction::class)->execute(
            CreatePendingTransactionData::fromArray([
                'ledger_id' => $ledger->id,
                'user_id' => $user->id,
                'payer_account_id' => $foreignAccount->id,
                'raw_data' => [],
                'source' => TransactionSource::Mcp->value,
            ]),
        );
    }
}
