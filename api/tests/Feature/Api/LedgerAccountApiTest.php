<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LedgerAccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_and_create_accounts_for_ledger(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/ledgers/{$ledger->id}/accounts", [
            'name' => 'House Pool',
            'type' => 'pool',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'House Pool')
            ->assertJsonPath('data.ledger_id', $ledger->id);
    }

    public function test_non_member_cannot_access_ledger_accounts(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts")
            ->assertForbidden();
    }

    public function test_account_with_postings_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);
        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 1000,
            'split_rule' => 'equal',
            'type' => 'manual',
            'participants' => [['account_id' => $participant->id, 'amount' => 1000]],
        ]);
        $participant->postings()->create([
            'transaction_id' => $transaction->id,
            'amount' => 1000,
            'direction' => 'debit',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$participant->id}")
            ->assertStatus(422);
    }
}
