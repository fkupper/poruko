<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\RecurringTransactionController;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('recurring-transactions')]
#[CoversClass(RecurringTransactionController::class)]
class RecurringTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function testMemberCanListRecurringTransactionsForLedger(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/recurring-transactions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $blueprint->id)
            ->assertJsonPath('data.0.amount', $blueprint->amount)
            ->assertJsonPath('data.0.status', 'active');
    }

    public function testMemberCanFilterRecurringTransactionsByStatus(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $activeBp = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'valid_to' => null,
        ]);

        $previousBp = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'valid_to' => '2026-01-01',
        ]);

        $deletedBp = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
        ]);
        $deletedBp->delete();

        Sanctum::actingAs($user, ['*']);

        // Filter active
        $this->getJson("/api/ledgers/{$ledger->id}/recurring-transactions?status=active")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeBp->id)
            ->assertJsonPath('data.0.status', 'active');

        // Filter previous_version
        $this->getJson("/api/ledgers/{$ledger->id}/recurring-transactions?status=previous_version")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $previousBp->id)
            ->assertJsonPath('data.0.status', 'previous_version');

        // Filter deleted
        $this->getJson("/api/ledgers/{$ledger->id}/recurring-transactions?status=deleted")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $deletedBp->id)
            ->assertJsonPath('data.0.status', 'deleted');
    }

    public function testMemberCanCreateRecurringTransaction(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $destination = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => \App\Enums\AccountType::SpaceExpense,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson("/api/ledgers/{$ledger->id}/recurring-transactions", [
            'payer_account_id' => $credit->id,
            'destination_account_id' => $destination->id,
            'amount' => 120000,
            'description' => 'Monthly Rent',
            'split_rule' => 'proportional',
            'participants' => [['user_id' => $user->id]],
            'start_date' => '2026-04-01',
            'frequency' => 'monthly',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', 120000)
            ->assertJsonPath('data.description', 'Monthly Rent')
            ->assertJsonPath('data.valid_from', '2026-04-01')
            ->assertJsonPath('data.frequency', 'monthly')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.payer_account_id', $credit->id)
            ->assertJsonPath('data.destination_account_id', $destination->id);

        $seriesId = $response->json('data.series_id');
        $this->assertIsString($seriesId);
        $this->assertNotEmpty($seriesId);

        $this->assertDatabaseHas('recurring_transactions', [
            'ledger_id' => $ledger->id,
            'series_id' => $seriesId,
            'amount' => 120000,
            'description' => 'Monthly Rent',
            'destination_account_id' => $destination->id,
        ]);
    }

    public function testMemberCanUpdateRecurringTransactionViaBiTemporalEdit(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'amount' => 100000,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson("/api/ledgers/{$ledger->id}/recurring-transactions/{$blueprint->id}", [
            'amount' => 110000,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.amount', 110000)
            ->assertJsonPath('data.series_id', $blueprint->series_id);

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $blueprint->id,
            'series_id' => $blueprint->series_id,
            'valid_to' => now()->subDay()->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('recurring_transactions', [
            'ledger_id' => $ledger->id,
            'series_id' => $blueprint->series_id,
            'amount' => 110000,
            'valid_from' => now()->format('Y-m-d'),
            'valid_to' => null,
        ]);

        $newId = $response->json('data.id');
        $this->assertNotSame($blueprint->id, $newId);
        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $newId,
            'series_id' => $blueprint->series_id,
        ]);
    }

    public function testCannotUpdateClosedRecurringTransaction(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'amount' => 100000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-02-01',
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->patchJson("/api/ledgers/{$ledger->id}/recurring-transactions/{$blueprint->id}", [
            'amount' => 110000,
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot update a closed recurring transaction. Create a new one instead.');

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $blueprint->id,
            'amount' => 100000,
            'valid_to' => '2026-02-01',
        ]);
    }

    public function testMemberCanSoftDeleteRecurringTransaction(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/ledgers/{$ledger->id}/recurring-transactions/{$blueprint->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('recurring_transactions', ['id' => $blueprint->id]);
    }

    public function testNonMemberCannotCreateRecurringTransactionForForeignLedger(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/recurring-transactions", [
            'payer_account_id' => $credit->id,
            'destination_account_id' => $credit->id,
            'amount' => 1000,
            'start_date' => '2026-04-01',
            'split_rule' => 'equal',
            'participants' => [],
        ])->assertForbidden();
    }
}
