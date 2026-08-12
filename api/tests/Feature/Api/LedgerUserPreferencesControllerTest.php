<?php

namespace Tests\Feature\Api;

use App\Enums\AccountType;
use App\Http\Controllers\Api\LedgerUserPreferencesController;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledgers')]
#[CoversClass(LedgerUserPreferencesController::class)]
class LedgerUserPreferencesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testCanUpdateUserPreferences(): void
    {
        // Arrange
        [$user, $ledger, $paymentAccount, $expenseAccount] = $this->seedMemberWithAccounts();

        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->putJson(route('ledgers.my-preferences.update', $ledger), [
            'default_payment_account_id' => $paymentAccount->id,
            'default_expense_account_id' => $expenseAccount->id,
        ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.my_preferences.default_payment_account_id', $paymentAccount->id)
            ->assertJsonPath('data.my_preferences.default_expense_account_id', $expenseAccount->id);

        $this->assertDatabaseHas('ledger_user', [
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'default_payment_account_id' => $paymentAccount->id,
            'default_expense_account_id' => $expenseAccount->id,
        ]);
    }

    public function testCanSetPoolAssetAsDefaultPaymentAccount(): void
    {
        // Arrange
        [$user, $ledger, , $expenseAccount] = $this->seedMemberWithAccounts();
        $housePool = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => AccountType::PoolAsset,
            'name' => 'House Joint Account',
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->putJson(route('ledgers.my-preferences.update', $ledger), [
            'default_payment_account_id' => $housePool->id,
            'default_expense_account_id' => $expenseAccount->id,
        ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.my_preferences.default_payment_account_id', $housePool->id)
            ->assertJsonPath('data.my_preferences.default_expense_account_id', $expenseAccount->id);

        $this->assertDatabaseHas('ledger_user', [
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'default_payment_account_id' => $housePool->id,
            'default_expense_account_id' => $expenseAccount->id,
        ]);
    }

    public function testUserLiabilityCannotBeDefaultPaymentAccount(): void
    {
        // Arrange
        [$user, $ledger] = $this->seedMemberWithAccounts();
        $liabilityAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => AccountType::UserLiability,
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act & Assert
        $this->putJson(route('ledgers.my-preferences.update', $ledger), [
            'default_payment_account_id' => $liabilityAccount->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_payment_account_id']);
    }

    public function testPoolAssetCannotBeDefaultExpenseAccount(): void
    {
        // Arrange
        [$user, $ledger] = $this->seedMemberWithAccounts();
        $poolAsset = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => AccountType::PoolAsset,
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act & Assert
        $this->putJson(route('ledgers.my-preferences.update', $ledger), [
            'default_expense_account_id' => $poolAsset->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_expense_account_id']);
    }

    public function testNonMemberCannotUpdatePreferences(): void
    {
        // Arrange
        $outsider = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $member = User::factory()->create();
        $ledger->users()->attach($member->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Admin');

        Sanctum::actingAs($outsider, ['*']);

        // Act & Assert
        $this->putJson(route('ledgers.my-preferences.update', $ledger), [
            'default_payment_account_id' => null,
            'default_expense_account_id' => null,
        ])->assertForbidden();
    }

    public function testSoftDeletedMemberCannotUpdatePreferences(): void
    {
        // Arrange
        [$user, $ledger] = $this->seedMemberWithAccounts();

        LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->delete();

        Sanctum::actingAs($user, ['*']);

        // Act & Assert
        $this->putJson(route('ledgers.my-preferences.update', $ledger), [
            'default_payment_account_id' => null,
            'default_expense_account_id' => null,
        ])->assertForbidden();
    }

    public function testForeignLedgerAccountIdIsRejected(): void
    {
        // Arrange
        [$user, $ledger] = $this->seedMemberWithAccounts();
        $foreignLedger = Ledger::factory()->create();
        $foreignExpense = Account::factory()->create([
            'ledger_id' => $foreignLedger->id,
            'type' => AccountType::SpaceExpense,
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act & Assert
        $this->putJson(route('ledgers.my-preferences.update', $ledger), [
            'default_expense_account_id' => $foreignExpense->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_expense_account_id']);
    }

    public function testOtherUsersFundingAccountIsRejected(): void
    {
        // Arrange
        [$user, $ledger] = $this->seedMemberWithAccounts();
        $otherUser = User::factory()->create();
        $ledger->users()->attach($otherUser->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $otherUser->assignRole('Member');

        $otherFunding = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $otherUser->id,
            'type' => AccountType::UserFunding,
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act & Assert
        $this->putJson(route('ledgers.my-preferences.update', $ledger), [
            'default_payment_account_id' => $otherFunding->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_payment_account_id']);
    }

    /*
     * Seeders.
     */

    /**
     * @return array{0: User, 1: Ledger, 2: Account, 3: Account}
     */
    private function seedMemberWithAccounts(): array
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        $paymentAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => AccountType::UserFunding,
        ]);

        $expenseAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => AccountType::SpaceExpense,
        ]);

        return [$user, $ledger, $paymentAccount, $expenseAccount];
    }
}
