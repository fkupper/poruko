<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\LedgerAccountController;
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

#[Group('ledger-accounts')]
#[CoversClass(LedgerAccountController::class)]
class LedgerAccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function testMemberCanListAndCreateAccountsForLedger(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/ledgers/{$ledger->id}/accounts", [
            'name' => 'House Pool',
            'type' => 'pool',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'House Pool')
            ->assertJsonPath('data.ledger_id', $ledger->id);
    }

    public function testNonMemberCannotAccessLedgerAccounts(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts")
            ->assertForbidden();
    }

    public function testMemberCanShowUpdateAndDeleteAccountWithoutPostings(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $account = Account::factory()->create(['ledger_id' => $ledger->id, 'name' => 'Old Name']);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $account->id);

        $this->patchJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}", [
            'name' => 'New Name',
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertNoContent();
    }

    public function testAccountWithPostingsCannotBeDeleted(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $credit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $debit = Account::factory()->create(['ledger_id' => $ledger->id]);
        $transaction = Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 1000,
            'split_rule' => 'equal',
            'type' => 'manual',
            'participants' => [],
        ]);
        $debit->postings()->create([
            'transaction_id' => $transaction->id,
            'amount' => 1000,
            'direction' => 'debit',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$debit->id}")
            ->assertStatus(422);
    }

    public function testNonMemberCannotShowUpdateOrDeleteAccount(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $account = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertForbidden();
        $this->patchJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}", ['name' => 'Updated'])
            ->assertForbidden();
        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertForbidden();
    }

    #[DataProvider('unauthenticatedEndpointDataProvider')]
    public function testUnauthenticatedUserCannotAccessAccountEndpoints(string $method, string $uri, array $payload = []): void
    {
        $ledger = Ledger::factory()->create();
        $account = Account::factory()->create(['ledger_id' => $ledger->id]);

        $uri = str_replace(['{ledger}', '{account}'], [(string) $ledger->id, (string) $account->id], $uri);

        $response = match (mb_strtoupper($method)) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
            'PATCH' => $this->patchJson($uri, $payload),
            'DELETE' => $this->deleteJson($uri),
            default => throw new InvalidArgumentException("Unsupported method: {$method}"),
        };

        $response->assertUnauthorized();
    }

    public static function unauthenticatedEndpointDataProvider(): array
    {
        return [
            'index' => ['GET', '/api/ledgers/{ledger}/accounts'],
            'store' => ['POST', '/api/ledgers/{ledger}/accounts', ['name' => 'Pool', 'type' => 'pool']],
            'show' => ['GET', '/api/ledgers/{ledger}/accounts/{account}'],
            'update' => ['PATCH', '/api/ledgers/{ledger}/accounts/{account}', ['name' => 'Pool Updated']],
            'delete' => ['DELETE', '/api/ledgers/{ledger}/accounts/{account}'],
        ];
    }

    #[DataProvider('storeValidationDataProvider')]
    public function testStoreValidatesRequiredFieldsAndTypes(array $payload, string $expectedErrorKey): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->postJson("/api/ledgers/{$ledger->id}/accounts", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$expectedErrorKey]);
    }

    public static function storeValidationDataProvider(): array
    {
        return [
            'missing name' => [['type' => 'pool'], 'name'],
            'name empty' => [['name' => '', 'type' => 'pool'], 'name'],
            'name too long' => [['name' => str_repeat('a', 256), 'type' => 'pool'], 'name'],
            'invalid type' => [['name' => 'Valid Name', 'type' => 'invalid'], 'type'],
            'missing type' => [['name' => 'Valid Name'], 'type'],
        ];
    }

    #[DataProvider('updateValidationDataProvider')]
    public function testUpdateValidatesFieldsWhenProvided(array $payload, string $expectedErrorKey): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $account = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$expectedErrorKey]);
    }

    public static function updateValidationDataProvider(): array
    {
        return [
            'name empty when provided' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
            'invalid type' => [['type' => 'invalid'], 'type'],
        ];
    }

    public function testCrossLedgerAccountBindingReturnsNotFound(): void
    {
        $user = User::factory()->create();
        $ledgerA = Ledger::factory()->create();
        $ledgerB = Ledger::factory()->create();
        $ledgerA->users()->attach($user->id, ['role' => 'admin']);
        $ledgerB->users()->attach($user->id, ['role' => 'admin']);
        $accountInLedgerB = Account::factory()->create(['ledger_id' => $ledgerB->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledgerA->id}/accounts/{$accountInLedgerB->id}")
            ->assertNotFound();
        $this->patchJson("/api/ledgers/{$ledgerA->id}/accounts/{$accountInLedgerB->id}", [
            'name' => 'Should Not Update',
        ])->assertNotFound();
        $this->deleteJson("/api/ledgers/{$ledgerA->id}/accounts/{$accountInLedgerB->id}")
            ->assertNotFound();
    }
}
