<?php

namespace Tests\Unit\Actions;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
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
        $user = User::factory()->create();
        \App\Models\LedgerUser::query()->create(['ledger_id' => $ledger->id, 'user_id' => $user->id, 'role' => 'admin']);
        Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id, 'type' => \App\Enums\AccountType::UserLiability->value]);
        $credit = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::query()->where('ledger_id', $ledger->id)->where('type', \App\Enums\AccountType::SpaceExpense->value)->firstOrFail();

        $blueprint = RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
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
        $this->assertSame(TransactionSource::Blueprint, $transaction->source);
        $this->assertSame(
            ['recurring_transaction_id' => $blueprint->id],
            $transaction->source_metadata,
        );
        $this->assertSame($blueprint->id, $transaction->source_recurring_transaction_id);
        $this->assertSame('2026-03-01', $transaction->date->format('Y-m-d'));
        $this->assertCount(4, $transaction->postings);
    }
}
