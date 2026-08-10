<?php

namespace Tests\Feature\Console;

use App\Console\Commands\LedgerMaterializeCommand;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(LedgerMaterializeCommand::class)]
class LedgerMaterializeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testMaterializesActiveBlueprintIntoTransaction(): void
    {
        Carbon::setTestNow('2026-03-15 12:00:00');

        $ledger = Ledger::factory()->create([
            'settlement_timezone' => 'UTC',
        ]);
        $user = \App\Models\User::factory()->create();
        \App\Models\LedgerUser::query()->create(['ledger_id' => $ledger->id, 'user_id' => $user->id, 'role' => 'admin']);
        $credit = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 100000,
            'frequency' => 'monthly',
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        $this->artisan('ledger:materialize')
            ->assertExitCode(0)
            ->expectsOutput('Materialized 1 recurring transaction(s).');

        $this->assertDatabaseHas('transactions', [
            'ledger_id' => $ledger->id,
            'amount' => 100000,
            'type' => 'recurring',
            'date' => '2026-03-01',
        ]);
    }

    public function testSkipsAlreadyMaterializedBlueprint(): void
    {
        Carbon::setTestNow('2026-03-15 12:00:00');

        $ledger = Ledger::factory()->create(['settlement_timezone' => 'UTC']);
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 100000,
            'frequency' => 'monthly',
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'source_recurring_transaction_id' => $blueprint->id,
            'amount' => 100000,
            'type' => 'recurring',
            'date' => '2026-03-01',
        ]);

        $this->artisan('ledger:materialize')
            ->assertExitCode(0)
            ->expectsOutput('Materialized 0 recurring transaction(s).');

        $this->assertDatabaseCount('transactions', 1);
    }

    public function testSkipsSoftDeletedBlueprints(): void
    {
        Carbon::setTestNow('2026-03-15 12:00:00');

        $ledger = Ledger::factory()->create(['settlement_timezone' => 'UTC']);
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 100000,
            'frequency' => 'monthly',
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ])->delete();

        $this->artisan('ledger:materialize')
            ->assertExitCode(0)
            ->expectsOutput('Materialized 0 recurring transaction(s).');

        $this->assertDatabaseCount('transactions', 0);
    }
}
