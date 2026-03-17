<?php

namespace Tests\Unit\Actions;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Modules\Ledger\Actions\PostRecurringTransactionAction;
use App\Modules\Ledger\Data\MaterializeRecurringTransactionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(PostRecurringTransactionAction::class)]
class PostRecurringTransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function testItMaterializesBlueprintIntoTransactionWithSourceLink(): void
    {
        $ledger = Ledger::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 50000,
            'description' => 'Rent',
            'split_rule' => 'equal',
            'participants' => [],
        ]);

        $data = MaterializeRecurringTransactionData::fromBlueprintAndPeriod($blueprint->id, [
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
        ]);

        $action = app(PostRecurringTransactionAction::class);

        $transaction = $action->execute($data);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertSame(50000, $transaction->amount);
        $this->assertSame(TransactionType::Recurring, $transaction->type);
        $this->assertSame($blueprint->id, $transaction->source_recurring_transaction_id);
        $this->assertSame('2026-03-01', $transaction->date->format('Y-m-d'));
        $this->assertCount(2, $transaction->postings);
    }
}
