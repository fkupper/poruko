<?php

namespace Tests\Unit\Queries;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Modules\Ledger\Data\TransactionIndexFiltersData;
use App\Modules\Ledger\Queries\LedgerTransactionIndexQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(LedgerTransactionIndexQuery::class)]
class LedgerTransactionIndexQueryTest extends TestCase
{
    use RefreshDatabase;

    public function testItFiltersTransactionsByLedgerDatesAndCreditAccount(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $otherLedger = Ledger::factory()->create();
        $creditA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $creditB = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $otherCredit = Account::factory()->create(['ledger_id' => $otherLedger->id]);
        $otherDebit = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $expected = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $creditA->id,
            'debit_account_id' => $debit->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [],
            'date' => '2026-03-10',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $creditB->id,
            'debit_account_id' => $debit->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [],
            'date' => '2026-03-09',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $otherLedger->id,
            'credit_account_id' => $otherCredit->id,
            'debit_account_id' => $otherDebit->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [],
            'date' => '2026-03-10',
        ]);

        // Act
        $query = app(LedgerTransactionIndexQuery::class);
        $result = $query->execute($ledger, new TransactionIndexFiltersData(
            fromDate: '2026-03-10',
            toDate: '2026-03-10',
            accountId: $creditA->id,
        ));

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame($expected->id, $result->first()?->id);
    }

    public function testItReturnsTransactionsWhereAccountIsDebitButNotCredit(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $creditA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $creditB = Account::factory()->create(['ledger_id' => $ledger->id]);
        $sharedDebit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $otherDebit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $expected = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $creditA->id,
            'debit_account_id' => $sharedDebit->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [],
            'date' => '2026-03-10',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $creditB->id,
            'debit_account_id' => $otherDebit->id,
            'split_rule' => 'equal',
            'amount' => 2000,
            'participants' => [],
            'date' => '2026-03-10',
        ]);

        // Act
        $query = app(LedgerTransactionIndexQuery::class);
        $result = $query->execute($ledger, new TransactionIndexFiltersData(
            fromDate: '2026-03-10',
            toDate: '2026-03-10',
            accountId: $sharedDebit->id,
        ));

        // Assert
        $this->assertCount(1, $result);
        $this->assertSame($expected->id, $result->first()?->id);
    }
}
