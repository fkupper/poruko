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

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/recurring-transactions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $blueprint->id)
            ->assertJsonPath('data.0.amount', $blueprint->amount);
    }

    public function testMemberCanCreateRecurringTransaction(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/ledgers/{$ledger->id}/recurring-transactions", [
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
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
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('recurring_transactions', [
            'ledger_id' => $ledger->id,
            'amount' => 120000,
            'description' => 'Monthly Rent',
        ]);
    }

    public function testMemberCanUpdateRecurringTransactionViaBiTemporalEdit(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 100000,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/ledgers/{$ledger->id}/recurring-transactions/{$blueprint->id}", [
            'amount' => 110000,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.amount', 110000);

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $blueprint->id,
            'valid_to' => now()->subDay()->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('recurring_transactions', [
            'ledger_id' => $ledger->id,
            'amount' => 110000,
            'valid_from' => now()->format('Y-m-d'),
            'valid_to' => null,
        ]);
    }

    public function testMemberCanSoftDeleteRecurringTransaction(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/ledgers/{$ledger->id}/recurring-transactions/{$blueprint->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('recurring_transactions', ['id' => $blueprint->id]);
    }

    public function testNonMemberCannotCreateRecurringTransactionForForeignLedger(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/ledgers/{$ledger->id}/recurring-transactions", [
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 1000,
            'start_date' => '2026-04-01',
            'split_rule' => 'equal',
            'participants' => [],
        ])->assertForbidden();
    }
}
