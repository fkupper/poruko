<?php

namespace Tests\Unit\Actions;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostManualTransactionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_balanced_postings_for_equal_split_with_remainder(): void
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $payer = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
        ]);
        $participantA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantB = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantC = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        $transaction = $action->execute([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Dinner',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [
                ['account_id' => $participantA->id],
                ['account_id' => $participantB->id],
                ['account_id' => $participantC->id],
            ],
        ]);

        $this->assertSame(100, $transaction->amount);
        $this->assertCount(4, $transaction->postings);
        $this->assertSame(100, (int) $transaction->postings->where('direction', 'credit')->sum('amount'));
        $this->assertSame(100, (int) $transaction->postings->where('direction', 'debit')->sum('amount'));
        $this->assertSame(
            [33, 33, 34],
            $transaction->postings
                ->where('direction', 'debit')
                ->pluck('amount')
                ->sort()
                ->values()
                ->all(),
        );
    }

    public function test_it_rejects_individual_split_when_amounts_do_not_sum_to_total(): void
    {
        $ledger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participantB = Account::factory()->create(['ledger_id' => $ledger->id]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('must equal transaction amount');

        $action->execute([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Groceries',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'individual',
            'participants' => [
                ['account_id' => $participantA->id, 'amount' => 30],
                ['account_id' => $participantB->id, 'amount' => 60],
            ],
        ]);
    }

    public function test_it_rejects_accounts_outside_the_target_ledger(): void
    {
        $ledger = Ledger::factory()->create();
        $otherLedger = Ledger::factory()->create();
        $payer = Account::factory()->create(['ledger_id' => $ledger->id]);
        $foreignAccount = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $action = app(PostManualTransactionAction::class);

        $this->expectException(InvalidLedgerPostingException::class);
        $this->expectExceptionMessage('do not belong to the target ledger');

        $action->execute([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'amount' => 100,
            'description' => 'Subscription',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [
                ['account_id' => $foreignAccount->id],
            ],
        ]);
    }
}
