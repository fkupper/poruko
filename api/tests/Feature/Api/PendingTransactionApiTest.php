<?php

namespace Tests\Feature\Api;

use App\Enums\AccountType;
use App\Enums\PendingTransactionStatus;
use App\Enums\TransactionSource;
use App\Http\Controllers\Api\PendingTransactionController;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('pending-transactions')]
#[CoversClass(PendingTransactionController::class)]
class PendingTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function testMemberCanListPendingTransactionsForLedger(): void
    {
        [$ledger, $user, $payerAccount, $destinationAccount] = $this->createLedgerWithAdmin();

        $pending = $this->createPendingTransaction(
            $ledger,
            $user,
            $payerAccount,
            $destinationAccount,
        );
        PendingTransaction::factory()->rejected()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/pending-transactions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.source', TransactionSource::AiImport->value)
            ->assertJsonPath('data.0.payer_account_name', $payerAccount->name)
            ->assertJsonPath('data.0.destination_account_name', $destinationAccount->name);
    }

    public function testMemberCanApprovePendingTransactionIdempotently(): void
    {
        [$ledger, $user, $payerAccount, $destinationAccount] = $this->createLedgerWithAdmin();
        $pending = $this->createPendingTransaction(
            $ledger,
            $user,
            $payerAccount,
            $destinationAccount,
        );

        Sanctum::actingAs($user, ['*']);

        $endpoint = "/api/ledgers/{$ledger->id}/pending-transactions/{$pending->id}/approve";
        $response = $this->postJson($endpoint);

        $response->assertOk()
            ->assertJsonPath('data.source', TransactionSource::AiImport->value)
            ->assertJsonPath('data.source_metadata.pending_transaction_id', $pending->id)
            ->assertJsonPath('data.source_metadata.proposed_by_user_id', $user->id)
            ->assertJsonCount(4, 'data.postings');

        $transactionId = (int) $response->json('data.id');

        $this->assertDatabaseHas('pending_transactions', [
            'id' => $pending->id,
            'status' => PendingTransactionStatus::Approved->value,
            'reviewed_by_user_id' => $user->id,
            'committed_transaction_id' => $transactionId,
        ]);
        $this->assertSame(4, Transaction::query()->findOrFail($transactionId)->postings()->count());

        $this->postJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.id', $transactionId);

        $this->assertDatabaseCount('transactions', 1);
    }

    public function testMemberCanApprovePendingTransactionsInBatch(): void
    {
        [$ledger, $user, $payerAccount, $destinationAccount] = $this->createLedgerWithAdmin();
        $first = $this->createPendingTransaction($ledger, $user, $payerAccount, $destinationAccount);
        $second = $this->createPendingTransaction($ledger, $user, $payerAccount, $destinationAccount);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/pending-transactions/approve-batch", [
            'pending_transaction_ids' => [$first->id, $second->id],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseCount('postings', 8);
        $this->assertDatabaseMissing('pending_transactions', [
            'ledger_id' => $ledger->id,
            'status' => PendingTransactionStatus::Pending->value,
        ]);
    }

    public function testMemberCanRejectOneOrManyWithoutCreatingPostings(): void
    {
        [$ledger, $user, $payerAccount, $destinationAccount] = $this->createLedgerWithAdmin();
        $first = $this->createPendingTransaction($ledger, $user, $payerAccount, $destinationAccount);
        $second = $this->createPendingTransaction($ledger, $user, $payerAccount, $destinationAccount);
        $third = $this->createPendingTransaction($ledger, $user, $payerAccount, $destinationAccount);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/pending-transactions/{$first->id}/reject", [
            'reason' => 'Duplicate',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PendingTransactionStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'Duplicate');

        $this->postJson("/api/ledgers/{$ledger->id}/pending-transactions/reject-batch", [
            'pending_transaction_ids' => [$second->id, $third->id],
            'reason' => 'Not household expenses',
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('postings', 0);
    }

    public function testRejectedTransactionCannotBeApproved(): void
    {
        [$ledger, $user, $payerAccount, $destinationAccount] = $this->createLedgerWithAdmin();
        $pending = $this->createPendingTransaction($ledger, $user, $payerAccount, $destinationAccount);
        $pending->update(['status' => PendingTransactionStatus::Rejected]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/pending-transactions/{$pending->id}/approve")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Rejected transactions cannot be approved.');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function testMemberCanCompletePendingDetailsBeforeApproval(): void
    {
        [$ledger, $user, $payerAccount, $destinationAccount] = $this->createLedgerWithAdmin();
        $pending = PendingTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'payer_account_id' => null,
            'destination_account_id' => null,
            'suggested_amount' => 2500,
            'suggested_split_rule' => 'equal',
            'suggested_participants' => [],
            'date' => '2026-09-19',
            'source' => TransactionSource::AiImport,
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->patchJson("/api/ledgers/{$ledger->id}/pending-transactions/{$pending->id}", [
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $destinationAccount->id,
            'description' => 'Reviewed groceries',
        ])
            ->assertOk()
            ->assertJsonPath('data.payer_account_id', $payerAccount->id)
            ->assertJsonPath('data.destination_account_id', $destinationAccount->id)
            ->assertJsonPath('data.suggested_description', 'Reviewed groceries');
    }

    public function testNonMemberCannotListOrReviewPendingTransactions(): void
    {
        [$ledger, $member, $payerAccount, $destinationAccount] = $this->createLedgerWithAdmin();
        $pending = $this->createPendingTransaction(
            $ledger,
            $member,
            $payerAccount,
            $destinationAccount,
        );
        $nonMember = User::factory()->create();

        Sanctum::actingAs($nonMember, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/pending-transactions")->assertForbidden();
        $this->postJson("/api/ledgers/{$ledger->id}/pending-transactions/{$pending->id}/approve")
            ->assertForbidden();
        $this->postJson("/api/ledgers/{$ledger->id}/pending-transactions/approve-batch", [
            'pending_transaction_ids' => [$pending->id],
        ])->assertForbidden();
    }

    public function testCrossLedgerPendingTransactionBindingReturnsNotFound(): void
    {
        [$ledgerA, $user] = $this->createLedgerWithAdmin();
        [$ledgerB, $otherUser, $payerAccount, $destinationAccount] = $this->createLedgerWithAdmin();
        $pending = $this->createPendingTransaction(
            $ledgerB,
            $otherUser,
            $payerAccount,
            $destinationAccount,
        );

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/ledgers/{$ledgerA->id}/pending-transactions/{$pending->id}/approve")
            ->assertNotFound();
    }

    /**
     * @return array{Ledger, User, Account, Account}
     */
    private function createLedgerWithAdmin(): array
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $payerAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
        ]);
        $destinationAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', AccountType::SpaceExpense->value)
            ->firstOrFail();

        return [$ledger, $user, $payerAccount, $destinationAccount];
    }

    private function createPendingTransaction(
        Ledger $ledger,
        User $user,
        Account $payerAccount,
        Account $destinationAccount,
    ): PendingTransaction {
        return PendingTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $destinationAccount->id,
            'suggested_description' => 'Imported groceries',
            'suggested_amount' => 2500,
            'suggested_split_rule' => 'equal',
            'suggested_participants' => [['user_id' => $user->id]],
            'date' => '2026-09-19',
            'source' => TransactionSource::AiImport->value,
            'confidence' => 0.9500,
            'rationale' => 'Matched the merchant and amount.',
        ]);
    }
}
