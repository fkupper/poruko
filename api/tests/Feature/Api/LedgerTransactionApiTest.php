<?php

namespace Tests\Feature\Api;

use App\Enums\TransactionSource;
use App\Http\Controllers\Api\LedgerTransactionController;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\LedgerUser;
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
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $otherLedger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($otherLedger->id);
        $user->assignRole('Admin');

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $otherCredit = Account::factory()->create(['ledger_id' => $otherLedger->id]);
        $otherDebit = Account::factory()->create(['ledger_id' => $otherLedger->id]);

        $inLedger = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'amount' => 1000,
            'split_rule' => 'equal',
            'participants' => [],
            'date' => '2026-03-10',
        ]);
        $posting = $inLedger->postings()->create([
            'account_id' => $credit->id,
            'amount' => 1000,
            'direction' => 'credit',
        ]);

        Transaction::factory()->create([
            'ledger_id' => $otherLedger->id,
            'payer_account_id' => $otherCredit->id,
            'amount' => 2000,
            'split_rule' => 'equal',
            'participants' => [],
            'date' => '2026-03-10',
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act & Assert
        $this->getJson("/api/ledgers/{$ledger->id}/transactions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inLedger->id)
            ->assertJsonPath('data.0.postings.0.account_name', $credit->name);

        $this->getJson("/api/ledgers/{$ledger->id}/transactions/{$inLedger->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $inLedger->id)
            ->assertJsonPath('data.source', TransactionSource::Manual->value)
            ->assertJsonPath('data.payer_account_name', $credit->name)
            ->assertJsonPath('data.destination_account_name', $inLedger->fresh()->destinationAccount?->name)
            ->assertJsonPath('data.postings.0.id', $posting->id)
            ->assertJsonPath('data.postings.0.account_id', $credit->id)
            ->assertJsonPath('data.postings.0.account_name', $credit->name)
            ->assertJsonStructure(['data' => ['postings']]);
    }

    public function testMemberCanCreateManualTransactionAndPostings(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $payerAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'type' => \App\Enums\AccountType::SpaceExpense->value]);

        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $spaceExpenseAccount->id,
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
            ->assertJsonPath('data.source', TransactionSource::Manual->value)
            ->assertJsonPath('data.source_metadata', null)
            ->assertJsonPath('data.destination_account_name', $spaceExpenseAccount->name)
            ->assertJsonPath('data.postings.0.account_name', $payerAccount->name)
            ->assertJsonCount(4, 'data.postings');
    }

    public function testNonMemberCannotCreateTransactionForForeignLedger(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user, ['*']);

        // Act & Assert
        $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $credit->id,
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
        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
        ]);

        Sanctum::actingAs($user, ['*']);

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
        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
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
                'payer_account_id' => $credit->id,
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
        setPermissionsTeamId($ledgerA->id);
        $user->assignRole('Admin');
        $ledgerB->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledgerB->id);
        $user->assignRole('Admin');
        $credit = Account::factory()->create(['ledger_id' => $ledgerB->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledgerB->id]);
        $transactionInLedgerB = Transaction::factory()->create([
            'ledger_id' => $ledgerB->id,
            'payer_account_id' => $credit->id,
        ]);

        Sanctum::actingAs($user, ['*']);

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
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $creditA = Account::factory()->create(['ledger_id' => $ledger->id]);
        $creditB = Account::factory()->create(['ledger_id' => $ledger->id]);

        $match = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $creditA->id,
            'amount' => 1000,
            'split_rule' => 'equal',
            'participants' => [],
            'date' => '2026-03-10',
        ]);
        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $creditB->id,
            'amount' => 2000,
            'split_rule' => 'equal',
            'participants' => [],
            'date' => '2026-03-09',
        ]);

        Sanctum::actingAs($user, ['*']);

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
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $credit = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => \App\Enums\AccountType::UserFunding->value,
            'owner_id' => $user->id,
        ]);
        $spaceExpenseAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', \App\Enums\AccountType::SpaceExpense->value)
            ->firstOrFail();
        $poolAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => \App\Enums\AccountType::PoolAsset->value,
            'owner_id' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $fullPayload = array_merge([
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 1000,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'split_rule' => 'equal',
            'participants' => [],
        ], $payload);

        if (($fullPayload['destination_account_id'] ?? null) === '__pool__') {
            $fullPayload['destination_account_id'] = $poolAccount->id;
        }

        if ($participantUserIndices !== null && isset($fullPayload['participants'])) {
            foreach ($fullPayload['participants'] as $i => $participant) {
                $fullPayload['participants'][$i]['user_id'] = $users[$participantUserIndices[$i]]->id;
            }
        }

        $this->postJson("/api/ledgers/{$ledger->id}/transactions", $fullPayload)
            ->assertStatus(422);
    }

    public function testStoreRejectsZeroAmountWithFriendlyMessage(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $credit = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => \App\Enums\AccountType::UserFunding->value,
            'owner_id' => $user->id,
        ]);
        $spaceExpenseAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', \App\Enums\AccountType::SpaceExpense->value)
            ->firstOrFail();

        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $credit->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 0,
            'description' => 'Invalid',
            'date' => '2026-03-10',
            'split_rule' => 'equal',
            'participants' => [],
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'The amount must be greater than zero.');
    }

    public static function storeValidationDataProvider(): array
    {
        return [
            'zero amount' => [['amount' => 0], null],

            'missing payer_account_id' => [
                ['payer_account_id' => null],
                null,
            ],
            'destination not space expense' => [
                ['destination_account_id' => '__pool__'],
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
            'manual split with empty participants' => [
                [
                    'split_rule' => 'manual',
                    'participants' => [],
                ],
                null,
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

    public function testCannotUseSoftDeletedMemberAsParticipantOnStore(): void
    {
        // Arrange
        $admin = User::factory()->create();
        $removedMember = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($admin->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $admin->assignRole('Admin');
        $ledger->users()->attach($removedMember->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $removedMember->assignRole('Member');

        LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $removedMember->id)
            ->firstOrFail()
            ->delete();

        $payerAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $admin->id]);
        $spaceExpenseAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => \App\Enums\AccountType::SpaceExpense->value,
        ]);

        Sanctum::actingAs($admin, ['*']);

        // Act & Assert
        $this->postJson("/api/ledgers/{$ledger->id}/transactions", [
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 1000,
            'description' => 'Pizza',
            'date' => '2026-03-10',
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [
                ['user_id' => $removedMember->id],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['participants.0.user_id']);
    }

    public function testCannotUseSoftDeletedMemberAsParticipantOnUpdate(): void
    {
        // Arrange
        $admin = User::factory()->create();
        $removedMember = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($admin->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $admin->assignRole('Admin');
        $ledger->users()->attach($removedMember->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $removedMember->assignRole('Member');

        LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $removedMember->id)
            ->firstOrFail()
            ->delete();

        $payerAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $admin->id]);
        $spaceExpenseAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => \App\Enums\AccountType::SpaceExpense->value,
        ]);

        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 1000,
            'description' => 'Original',
            'date' => '2026-03-10',
            'split_rule' => 'equal',
            'participants' => [],
        ]);

        Sanctum::actingAs($admin, ['*']);

        // Act & Assert
        $this->patchJson("/api/ledgers/{$ledger->id}/transactions/{$transaction->id}", [
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 2000,
            'description' => 'Updated Pizza',
            'date' => '2026-03-11',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $removedMember->id]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['participants.0.user_id']);
    }

    public function testMemberCanUpdateTransaction(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $payerAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'owner_id' => $user->id]);
        $spaceExpenseAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'type' => \App\Enums\AccountType::SpaceExpense->value]);

        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 1000,
            'description' => 'Original',
            'date' => '2026-03-10',
            'split_rule' => 'equal',
            'participants' => [],
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->patchJson("/api/ledgers/{$ledger->id}/transactions/{$transaction->id}", [
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 2000,
            'description' => 'Updated Pizza',
            'date' => '2026-03-11',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.amount', 2000)
            ->assertJsonPath('data.description', 'Updated Pizza');
    }

    public function testMemberCanDeleteTransaction(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'amount' => 1000,
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act & Assert
        $this->deleteJson("/api/ledgers/{$ledger->id}/transactions/{$transaction->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function testCannotUpdateOrDeleteSettledTransaction(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $payerAccount = Account::factory()->create(['ledger_id' => $ledger->id]);
        $spaceExpenseAccount = Account::factory()->create(['ledger_id' => $ledger->id, 'type' => \App\Enums\AccountType::SpaceExpense->value]);
        $settlement = \App\Models\Settlement::factory()->create(['ledger_id' => $ledger->id]);

        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'settlement_id' => $settlement->id,
            'amount' => 1000,
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act & Assert (Update)
        $this->patchJson("/api/ledgers/{$ledger->id}/transactions/{$transaction->id}", [
            'payer_account_id' => $payerAccount->id,
            'destination_account_id' => $spaceExpenseAccount->id,
            'amount' => 2000,
            'date' => '2026-03-10',
            'split_rule' => 'equal',
        ])->assertStatus(422);

        // Act & Assert (Delete)
        $this->deleteJson("/api/ledgers/{$ledger->id}/transactions/{$transaction->id}")
            ->assertStatus(422);
    }
}
