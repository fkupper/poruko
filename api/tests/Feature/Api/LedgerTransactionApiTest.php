<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LedgerTransactionApiTest extends TestCase
{
    use RefreshDatabase;

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
}
