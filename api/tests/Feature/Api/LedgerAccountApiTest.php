<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\LedgerAccountController;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LedgerAccountController::class)]
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

    public function test_member_can_show_update_and_delete_account_without_postings(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $account = Account::factory()->create(['ledger_id' => $ledger->id, 'name' => 'Old Name']);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $account->id);

        $this->patchJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}", [
            'name' => 'New Name',
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertNoContent();
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

    public function test_non_member_cannot_show_update_or_delete_account(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $account = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertForbidden();
        $this->patchJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}", ['name' => 'Updated'])
            ->assertForbidden();
        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_account_endpoints(): void
    {
        $ledger = Ledger::factory()->create();
        $account = Account::factory()->create(['ledger_id' => $ledger->id]);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts")->assertUnauthorized();
        $this->postJson("/api/ledgers/{$ledger->id}/accounts", [
            'name' => 'Pool',
            'type' => 'pool',
        ])->assertUnauthorized();
        $this->getJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")->assertUnauthorized();
        $this->patchJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}", ['name' => 'Pool Updated'])->assertUnauthorized();
        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")->assertUnauthorized();
    }

    public function test_cross_ledger_account_binding_returns_not_found(): void
    {
        $user = User::factory()->create();
        $ledgerA = Ledger::factory()->create();
        $ledgerB = Ledger::factory()->create();
        $ledgerA->users()->attach($user->id, ['role' => 'admin']);
        $ledgerB->users()->attach($user->id, ['role' => 'admin']);
        $accountInLedgerB = Account::factory()->create(['ledger_id' => $ledgerB->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledgerA->id}/accounts/{$accountInLedgerB->id}")
            ->assertNotFound();
        $this->patchJson("/api/ledgers/{$ledgerA->id}/accounts/{$accountInLedgerB->id}", [
            'name' => 'Should Not Update',
        ])->assertNotFound();
        $this->deleteJson("/api/ledgers/{$ledgerA->id}/accounts/{$accountInLedgerB->id}")
            ->assertNotFound();
    }
}
