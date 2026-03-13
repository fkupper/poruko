<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\LedgerController;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LedgerController::class)]
class LedgerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_their_ledgers(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ledgerA = Ledger::factory()->create(['name' => 'Home Ledger']);
        $ledgerB = Ledger::factory()->create(['name' => 'Trip Ledger']);
        $foreignLedger = Ledger::factory()->create(['name' => 'Foreign Ledger']);

        $ledgerA->users()->attach($user->id, ['role' => 'admin']);
        $ledgerB->users()->attach($user->id, ['role' => 'member']);
        $foreignLedger->users()->attach($otherUser->id, ['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->getJson('/api/ledgers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $ledgerA->id)
            ->assertJsonPath('data.0.name', $ledgerA->name)
            ->assertJsonPath('data.0.settlement_mode', $ledgerA->settlement_mode)
            ->assertJsonPath('data.0.pool_base_budget', $ledgerA->pool_base_budget)
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.1.id', $ledgerB->id)
            ->assertJsonPath('data.1.name', $ledgerB->name)
            ->assertJsonPath('data.1.settlement_mode', $ledgerB->settlement_mode)
            ->assertJsonPath('data.1.pool_base_budget', $ledgerB->pool_base_budget)
            ->assertJsonPath('data.1.users_count', 1);
    }

    public function test_authenticated_user_with_no_ledgers_gets_empty_list(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/ledgers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_user_cannot_list_ledgers(): void
    {
        $this->getJson('/api/ledgers')
            ->assertUnauthorized();
    }
}

