<?php

namespace Tests\Unit\Actions;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Modules\Ledger\Actions\UpdateRecurringTransactionAction;
use App\Modules\Ledger\Data\UpdateRecurringTransactionData;
use App\Modules\Ledger\Exceptions\CannotUpdateClosedRecurringTransactionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(UpdateRecurringTransactionAction::class)]
class UpdateRecurringTransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function testItPerformsBiTemporalEdit(): void
    {
        $ledger = Ledger::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'amount' => 100000,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        $action = app(UpdateRecurringTransactionAction::class);

        $data = new UpdateRecurringTransactionData(amount: 110000);
        $updated = $action->execute($blueprint, $data);

        $this->assertSame(110000, $updated->amount);
        $this->assertNotSame($blueprint->id, $updated->id);

        $blueprint->refresh();
        $this->assertNotNull($blueprint->valid_to);

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $blueprint->id,
        ]);
        $this->assertDatabaseHas('recurring_transactions', [
            'ledger_id' => $ledger->id,
            'amount' => 110000,
            'valid_to' => null,
        ]);
    }

    public function testItThrowsWhenUpdatingClosedBlueprint(): void
    {
        $ledger = Ledger::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-05-31',
        ]);

        $action = app(UpdateRecurringTransactionAction::class);

        $this->expectException(CannotUpdateClosedRecurringTransactionException::class);

        $action->execute($blueprint, new UpdateRecurringTransactionData(amount: 120000));
    }
}
