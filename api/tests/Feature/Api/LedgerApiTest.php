<?php

namespace Tests\Feature\Api;

use App\Enums\SettlementMode;
use App\Http\Controllers\Api\LedgerController;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledgers')]
#[CoversClass(LedgerController::class)]
class LedgerApiTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthenticatedUserCanListTheirLedgers(): void
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
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.1.id', $ledgerB->id)
            ->assertJsonPath('data.1.name', $ledgerB->name)
            ->assertJsonPath('data.1.settlement_mode', $ledgerB->settlement_mode)
            ->assertJsonPath('data.1.users_count', 1);
    }

    public function testAuthenticatedUserWithNoLedgersGetsEmptyList(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/ledgers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testUnauthenticatedUserCannotListLedgers(): void
    {
        $this->getJson('/api/ledgers')
            ->assertUnauthorized();
    }

    public function testAuthenticatedUserCanCreateLedger(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/ledgers', [
            'name' => 'New Household',
            'currency' => 'USD',
            'settlement_mode' => SettlementMode::JointClearinghouse->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Household')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.currency_symbol', '$')
            ->assertJsonPath('data.settlement_mode', SettlementMode::JointClearinghouse->value);

        $this->assertDatabaseHas('ledgers', [
            'name' => 'New Household',
            'currency' => 'USD',
            'settlement_mode' => SettlementMode::JointClearinghouse->value,
        ]);
    }

    public function testCreateLedgerValidationFailsWithoutName(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/ledgers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function testCreateLedgerValidationFailsWithInvalidCurrency(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/ledgers', [
            'name' => 'Invalid Space',
            'currency' => 'INVALID_CODE',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    }
}
