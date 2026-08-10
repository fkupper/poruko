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
        $otherCredit = Account::factory()->create(['ledger_id' => $otherLedger->id]);
        $otherDebit = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $expected = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $creditA->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [],
            'date' => '2026-03-10',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $creditB->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [],
            'date' => '2026-03-09',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $otherLedger->id,
            'payer_account_id' => $otherCredit->id,
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
            'payer_account_id' => $creditA->id,
            'split_rule' => 'equal',
            'amount' => 1000,
            'participants' => [],
            'date' => '2026-03-10',
        ]);

        $expected->postings()->create([
            'account_id' => $sharedDebit->id,
            'amount' => 1000,
            'direction' => 'debit',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $creditB->id,
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

    public function testItFiltersTransactionsByCreatorUserIdsSplitRulesTypesAndSettlementId(): void
    {
        // Arrange
        $userA = \App\Models\User::factory()->create();
        $userB = \App\Models\User::factory()->create();
        $ledger = Ledger::factory()->create();

        $accountA = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userA->id]);
        $accountB = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $userB->id]);

        $settlement = \App\Models\Settlement::factory()->create(['ledger_id' => $ledger->id]);

        $tx1 = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $accountA->id,
            'split_rule' => 'proportional',
            'type' => 'manual',
            'settlement_id' => $settlement->id,
        ]);

        $tx2 = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $accountB->id,
            'split_rule' => 'equal',
            'type' => 'recurring',
            'settlement_id' => null,
        ]);

        $query = app(LedgerTransactionIndexQuery::class);

        // Filter by creatorUserIds
        $resCreator = $query->execute($ledger, new TransactionIndexFiltersData(creatorUserIds: [$userA->id]));
        $this->assertCount(1, $resCreator);
        $this->assertSame($tx1->id, $resCreator->first()?->id);

        // Filter by splitRules
        $resSplit = $query->execute($ledger, new TransactionIndexFiltersData(splitRules: ['equal']));
        $this->assertCount(1, $resSplit);
        $this->assertSame($tx2->id, $resSplit->first()?->id);

        // Filter by types
        $resType = $query->execute($ledger, new TransactionIndexFiltersData(types: ['manual']));
        $this->assertCount(1, $resType);
        $this->assertSame($tx1->id, $resType->first()?->id);

        // Filter by settlementId
        $resSettlement = $query->execute($ledger, new TransactionIndexFiltersData(settlementId: $settlement->id));
        $this->assertCount(1, $resSettlement);
        $this->assertSame($tx1->id, $resSettlement->first()?->id);
    }
}
