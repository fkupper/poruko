<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\LedgerTransactionController;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger-transactions')]
#[CoversClass(LedgerTransactionController::class)]
class LedgerTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function testMemberCanListAndShowTransactionsForLedger(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $otherLedger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $otherLedger->users()->attach($user->id, ['role' => 'admin']);

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $otherCredit = Account::factory()->create(['ledger_id' => $otherLedger->id]);
        $otherDebit = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $inLedger = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 1000,
            'split_rule' => 'equal',
            'participants' => [],
            'date' => '2026-03-10',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $otherLedger->id,
            'credit_account_id' => $otherCredit->id,
            'debit_account_id' => $otherDebit->id,
            'amount' => 2000,
            'split_rule' => 'equal',
            'participants' => [],
            'date' => '2026-03-10',
        ]);

        Sanctum::actingAs($user);

        // Act & Assert
        $this->getJson("/api/ledgers/{$ledger->id}/transactions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inLedger->id);

        $this->getJson("/api/ledgers/{$ledger->id}/transactions/{$inLedger->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $inLedger->id);
    }

    public function testMemberCanCreateManualTransactionAndPostings(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $creditAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $debitAccount = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        // Act
        $response = $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'credit_account_id' => $creditAccount->id,
            'debit_account_id' => $debitAccount->id,
            'amount' => 1000,
            'description' => 'Pizza',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [
                ['user_id' => $user->id],
            ],
        ]);

        // Assert
        $response->assertCreated()
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.split_rule', 'equal')
            ->assertJsonCount(2, 'data.postings');
    }

    public function testNonMemberCannotCreateTransactionForForeignLedger(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        // Act & Assert
        $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 1000,
            'description' => 'Pizza',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
        ])->assertForbidden();
    }

    public function testNonMemberCannotListOrShowTransactions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
        ]);

        Sanctum::actingAs($user);

        // Act & Assert
        $this->getJson("/api/ledgers/{$ledger->id}/transactions")->assertForbidden();
        $this->getJson("/api/ledgers/{$ledger->id}/transactions/{$transaction->id}")->assertForbidden();
    }

    #[DataProvider('unauthenticatedTransactionEndpointDataProvider')]
    public function testUnauthenticatedUserCannotAccessTransactionEndpoints(string $method, string $uri, array $payload = []): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'participants' => [],
        ]);

        $uri = str_replace(
            ['{ledger}', '{transaction}'],
            [(string) $ledger->id, (string) $transaction->id],
            $uri,
        );

        $response = match (mb_strtoupper($method)) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, array_merge([
                'credit_account_id' => $credit->id,
                'debit_account_id' => $debit->id,
                'amount' => 1000,
                'description' => 'Dinner',
                'date' => '2026-03-10',
                'split_rule' => 'equal',
                'participants' => [],
            ], $payload)),
            default => throw new InvalidArgumentException("Unsupported method: {$method}"),
        };

        $response->assertUnauthorized();
    }

    public static function unauthenticatedTransactionEndpointDataProvider(): array
    {
        return [
            'index' => ['GET', '/api/ledgers/{ledger}/transactions'],
            'store' => ['POST', '/api/ledgers/{ledger}/transactions'],
            'show' => ['GET', '/api/ledgers/{ledger}/transactions/{transaction}'],
        ];
    }

    public function testCrossLedgerTransactionBindingReturnsNotFound(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerA = Ledger::factory()->create();
        $ledgerB = Ledger::factory()->create();
        $ledgerA->users()->attach($user->id, ['role' => 'admin']);
        $ledgerB->users()->attach($user->id, ['role' => 'admin']);
        $credit = Account::factory()->create(['ledger_id' => $ledgerB->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledgerB->id]);
        $transactionInLedgerB = Transaction::factory()->create([
            'ledger_id' => $ledgerB->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
        ]);

        Sanctum::actingAs($user);

        // Act & Assert
        $this->getJson("/api/ledgers/{$ledgerA->id}/transactions/{$transactionInLedgerB->id}")
            ->assertNotFound();
    }

    public function testIndexFiltersByDateRangeAndAccountId(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $creditA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $creditB = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        $match = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $creditA->id,
            'debit_account_id' => $debit->id,
            'amount' => 1000,
            'split_rule' => 'equal',
            'participants' => [],
            'date' => '2026-03-10',
        ]);
        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $creditB->id,
            'debit_account_id' => $debit->id,
            'amount' => 2000,
            'split_rule' => 'equal',
            'participants' => [],
            'date' => '2026-03-09',
        ]);

        Sanctum::actingAs($user);

        // Act & Assert
        $this->getJson("/api/ledgers/{$ledger->id}/transactions?from_date=2026-03-10&to_date=2026-03-10&account_id={$creditA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<int>|null $participantUserIndices Indices into [$user, $user2] for each participant's user_id
     */
    #[DataProvider('storeValidationDataProvider')]
    public function testStoreValidatesNegativeCases(array $payload, ?array $participantUserIndices = null): void
    {
        // Arrange
        $user = User::factory()->create();
        $user2 = User::factory()->create();
        $users = [$user, $user2];
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $fullPayload = array_merge([
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 1000,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'split_rule' => 'equal',
            'participants' => [],
        ], $payload);

        if ($participantUserIndices !== null && isset($fullPayload['participants'])) {
            foreach ($fullPayload['participants'] as $i => $participant) {
                $fullPayload['participants'][$i]['user_id'] = $users[$participantUserIndices[$i]]->id;
            }
        }

        $this->postJson("/api/ledgers/{$ledger->id}/transactions", $fullPayload)
            ->assertStatus(422);
    }

    public static function storeValidationDataProvider(): array
    {
        return [
            'zero amount' => [['amount' => 0], null],
            'missing debit_account_id' => [
                ['debit_account_id' => null],
                null,
            ],
            'missing credit_account_id' => [
                ['credit_account_id' => null],
                null,
            ],
            'manual split without share' => [
                [
                    'split_rule' => 'manual',
                    'participants' => [
                        ['user_id' => 0],
                    ],
                ],
                [0],
            ],
            'manual split with zero share' => [
                [
                    'split_rule' => 'manual',
                    'participants' => [
                        ['user_id' => 0, 'share' => 0],
                    ],
                ],
                [0],
            ],
            'individual split with zero participants' => [
                [
                    'split_rule' => 'individual',
                    'participants' => [],
                ],
                null,
            ],
            'individual split with two participants' => [
                [
                    'split_rule' => 'individual',
                    'participants' => [
                        ['user_id' => 0],
                        ['user_id' => 0],
                    ],
                ],
                [0, 1],
            ],
        ];
    }
}
