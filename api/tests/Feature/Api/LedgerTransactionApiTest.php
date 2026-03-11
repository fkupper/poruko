<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\LedgerTransactionController;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LedgerTransactionController::class)]
class LedgerTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_and_show_transactions_for_ledger(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $otherLedger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $otherLedger->users()->attach($user->id, ['role' => 'admin']);

        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);
        $otherPayer = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $inLedger = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'split_rule' => 'equal',
            'participants' => [['account_id' => $participant->id, 'amount' => 1000]],
            'date' => '2026-03-10',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $otherLedger->id,
            'payer_account_id' => $otherPayer->id,
            'amount' => 2000,
            'split_rule' => 'equal',
            'participants' => [['account_id' => $otherPayer->id, 'amount' => 2000]],
            'date' => '2026-03-10',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/transactions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inLedger->id);

        $this->getJson("/api/ledgers/{$ledger->id}/transactions/{$inLedger->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $inLedger->id);
    }

    public function test_member_can_create_manual_transaction_and_postings(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $payer = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $participantA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantB = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'description' => 'Pizza',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [
                ['account_id' => $participantA->id],
                ['account_id' => $participantB->id],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.split_rule', 'equal')
            ->assertJsonCount(3, 'data.postings');
    }

    public function test_non_member_cannot_create_transaction_for_foreign_ledger(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();

        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'description' => 'Pizza',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [
                ['account_id' => $participant->id],
            ],
        ])->assertForbidden();
    }

    public function test_non_member_cannot_list_or_show_transactions(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/transactions")->assertForbidden();
        $this->getJson("/api/ledgers/{$ledger->id}/transactions/{$transaction->id}")->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_transaction_endpoints(): void
    {
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);
        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'participants' => [['account_id' => $participant->id, 'amount' => 1000]],
        ]);

        $this->getJson("/api/ledgers/{$ledger->id}/transactions")->assertUnauthorized();
        $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'description' => 'Dinner',
            'date' => '2026-03-10',
            'split_rule' => 'equal',
            'participants' => [['account_id' => $participant->id]],
        ])->assertUnauthorized();
        $this->getJson("/api/ledgers/{$ledger->id}/transactions/{$transaction->id}")->assertUnauthorized();
    }

    public function test_individual_split_amount_mismatch_returns_validation_style_error(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantB = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'description' => 'Utilities',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'individual',
            'participants' => [
                ['account_id' => $participantA->id, 'amount' => 300],
                ['account_id' => $participantB->id, 'amount' => 500],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Individual split participant amounts must equal transaction amount.');
    }

    public function test_cross_ledger_transaction_binding_returns_not_found(): void
    {
        $user = User::factory()->create();
        $ledgerA = Ledger::factory()->create();
        $ledgerB = Ledger::factory()->create();
        $ledgerA->users()->attach($user->id, ['role' => 'admin']);
        $ledgerB->users()->attach($user->id, ['role' => 'admin']);
        $payer = Account::factory()->create(['ledger_id' => $ledgerB->id]);
        $transactionInLedgerB = Transaction::factory()->create([
            'ledger_id' => $ledgerB->id,
            'payer_account_id' => $payer->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledgerA->id}/transactions/{$transactionInLedgerB->id}")
            ->assertNotFound();
    }

    public function test_index_filters_by_date_range_and_account_id(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $payerA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $payerB = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);

        $match = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payerA->id,
            'amount' => 1000,
            'split_rule' => 'equal',
            'participants' => [['account_id' => $participant->id, 'amount' => 1000]],
            'date' => '2026-03-10',
        ]);
        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payerB->id,
            'amount' => 2000,
            'split_rule' => 'equal',
            'participants' => [['account_id' => $participant->id, 'amount' => 2000]],
            'date' => '2026-03-09',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/transactions?from_date=2026-03-10&to_date=2026-03-10&account_id={$payerA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_store_validates_negative_cases(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $payer->id,
            'amount' => 0,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'split_rule' => 'equal',
            'participants' => [],
        ])->assertStatus(422);

        $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'split_rule' => 'individual',
            'participants' => [['account_id' => $participant->id]],
        ])->assertStatus(422);
    }
}
