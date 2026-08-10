<?php

namespace Tests\Feature\Api;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerUserPreferencesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testCanUpdateUserPreferences(): void
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

        $response = $this->actingAs($user)
            ->putJson(route('ledgers.my-preferences.update', $ledger), [
                'default_payment_account_id' => $paymentAccount->id,
                'default_expense_account_id' => $expenseAccount->id,
            ]);

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
}
