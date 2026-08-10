<?php

namespace Tests\Feature\Api;

use App\Enums\AccountType;
use App\Http\Controllers\Api\LedgerAccountController;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['is_main' => true]);

        $this->postJson("/api/ledgers/{$ledger->id}/accounts", [
            'name' => 'House Pool',
            'type' => 'pool_asset',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'House Pool')
            ->assertJsonPath('data.ledger_id', $ledger->id);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function testNonMemberCannotAccessLedgerAccounts(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts")
            ->assertForbidden();
    }

    public function testMemberCanShowUpdateAndDeleteAccountWithoutPostings(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $account = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::PoolAsset->value,
            'name' => 'Old Name',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $account->id)
            ->assertJsonPath('data.is_main', false);

        $this->patchJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}", [
            'name' => 'New Name',
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$account->id}")
            ->assertNoContent();
    }

    public function testCannotDeleteMainPersonalAccount(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $mainId = (int) DB::table('ledger_user')
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->value('main_personal_account_id');

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$mainId}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete your main personal account.');
    }

    public function testCannotDeleteLastPersonalAccountWhenPivotMainIsUnset(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $onlyPersonalId = (int) DB::table('ledger_user')
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->value('main_personal_account_id');

        DB::table('ledger_user')
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->update(['main_personal_account_id' => null]);

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$onlyPersonalId}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete your last personal account.');
    }

    public function testCanDeleteNonMainPersonalWhenAnotherPersonalExists(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $extraPersonal = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => AccountType::UserFunding->value,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$extraPersonal->id}")
            ->assertNoContent();
    }

    public function testAccountAttachedToProcessCannotBeDeleted(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $credit = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
        ]);
        $recurring = \App\Models\RecurringTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'payer_account_id' => $credit->id,
            'amount' => 1000,
            'split_rule' => 'equal',
            'participants' => [],
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/ledgers/{$ledger->id}/accounts/{$credit->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Account cannot be deleted because it is attached to an active process.');
    }

    public function testNonMemberCannotShowUpdateOrDeleteAccount(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $account = Account::factory()->create(['ledger_id' => $ledger->id]);

        Sanctum::actingAs($user, ['*']);

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
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        Sanctum::actingAs($user, ['*']);

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
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $account = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => null,
            'type' => AccountType::PoolAsset->value,
        ]);

        Sanctum::actingAs($user, ['*']);

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
        setPermissionsTeamId($ledgerA->id);
        $user->assignRole('Admin');
        $ledgerB->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledgerB->id);
        $user->assignRole('Admin');
        $accountInLedgerB = Account::factory()->create([
            'ledger_id' => $ledgerB->id,
            'owner_id' => null,
            'type' => AccountType::PoolAsset->value,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledgerA->id}/accounts/{$accountInLedgerB->id}")
            ->assertNotFound();
        $this->patchJson("/api/ledgers/{$ledgerA->id}/accounts/{$accountInLedgerB->id}", [
            'name' => 'Should Not Update',
        ])->assertNotFound();
        $this->deleteJson("/api/ledgers/{$ledgerA->id}/accounts/{$accountInLedgerB->id}")
            ->assertNotFound();
    }

    public function testCannotAssignSoftDeletedMemberAsAccountOwner(): void
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

        \App\Models\LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $removedMember->id)
            ->firstOrFail()
            ->delete();

        Sanctum::actingAs($admin, ['*']);

        // Act & Assert
        $this->postJson("/api/ledgers/{$ledger->id}/accounts", [
            'name' => 'Orphan Personal',
            'type' => AccountType::UserFunding->value,
            'owner_id' => $removedMember->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_id']);
    }
}
