<?php

namespace Tests\Unit\Queries;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Modules\Ledger\Data\TransactionIndexFiltersData;
use App\Modules\Ledger\Queries\LedgerTransactionIndexQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LedgerTransactionIndexQuery::class)]
class LedgerTransactionIndexQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_transactions_by_ledger_dates_and_account(): void
    {
        $ledger = Ledger::factory()->create();
        $otherLedger = Ledger::factory()->create();
        $payerA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $payerB = Account::factory()->create(['ledger_id' => $ledger->id]);
        $participant = Account::factory()->create(['ledger_id' => $ledger->id]);
        $otherPayer = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $expected = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payerA->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [['account_id' => $participant->id, 'amount' => 1000]],
            'date' => '2026-03-10',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payerB->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [['account_id' => $participant->id, 'amount' => 1000]],
            'date' => '2026-03-09',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $otherLedger->id,
            'payer_account_id' => $otherPayer->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [['account_id' => $otherPayer->id, 'amount' => 1000]],
            'date' => '2026-03-10',
        ]);

        $query = app(LedgerTransactionIndexQuery::class);

        $result = $query->execute($ledger, new TransactionIndexFiltersData(
            fromDate: '2026-03-10',
            toDate: '2026-03-10',
            accountId: $payerA->id,
        ));

        $this->assertCount(1, $result);
        $this->assertSame($expected->id, $result->first()?->id);
    }
}
