<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\FinancialProfileController;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FinancialProfileController::class)]
class FinancialProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_update_active_financial_profile(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active",
            [
                'incomes' => [
                    ['description' => 'Salary', 'amount' => 400000],
                    ['description' => 'Freelance', 'amount' => 50000],
                ],
                'deductions' => [
                    ['description' => 'Health Insurance', 'amount' => 15000],
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.ledger_id', $ledger->id)
            ->assertJsonPath('data.computed.total_income', 450000)
            ->assertJsonPath('data.computed.total_deductions', 15000)
            ->assertJsonPath('data.computed.shareable_income', 435000);

        $this->assertDatabaseCount('financial_profiles', 1);
    }

    public function test_update_is_idempotent_for_existing_active_profile(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => now()->startOfMonth()->format('Y-m-d'),
            'valid_to' => null,
            'incomes' => [['description' => 'Old Salary', 'amount' => 200000]],
            'deductions' => [],
        ]);

        Sanctum::actingAs($user);

        $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active",
            [
                'incomes' => [['description' => 'New Salary', 'amount' => 500000]],
                'deductions' => [['description' => 'Tax', 'amount' => 50000]],
            ],
        )->assertOk()
            ->assertJsonPath('data.computed.shareable_income', 450000);

        $this->assertDatabaseCount('financial_profiles', 1);
    }

    public function test_member_can_show_active_financial_profile(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => now()->startOfMonth()->format('Y-m-d'),
            'valid_to' => null,
            'incomes' => [['description' => 'Salary', 'amount' => 300000]],
            'deductions' => [['description' => 'Insurance', 'amount' => 10000]],
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active")
            ->assertOk()
            ->assertJsonPath('data.computed.shareable_income', 290000);
    }

    public function test_show_returns_not_found_when_no_active_profile_exists(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active")
            ->assertNotFound();
    }

    public function test_non_member_cannot_update_financial_profile(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($target->id, ['role' => 'member']);

        Sanctum::actingAs($user);

        $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$target->id}/financial-profile/active",
            [
                'incomes' => [['description' => 'Salary', 'amount' => 400000]],
                'deductions' => [],
            ],
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_financial_profile(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active",
            [
                'incomes' => [['description' => 'Salary', 'amount' => 400000]],
                'deductions' => [],
            ],
        )->assertUnauthorized();

        $this->getJson("/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active")
            ->assertUnauthorized();
    }

    public function test_member_cannot_update_another_members_financial_profile(): void
    {
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($memberA->id, ['role' => 'member']);
        $ledger->users()->attach($memberB->id, ['role' => 'member']);

        Sanctum::actingAs($memberA);

        $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$memberB->id}/financial-profile/active",
            [
                'incomes' => [['description' => 'Salary', 'amount' => 400000]],
                'deductions' => [],
            ],
        )->assertForbidden();
    }

    public function test_non_member_cannot_view_financial_profile(): void
    {
        $outsider = User::factory()->create();
        $member = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($member->id, ['role' => 'member']);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $member->id,
            'valid_from' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/ledgers/{$ledger->id}/users/{$member->id}/financial-profile/active")
            ->assertForbidden();
    }

    public function test_update_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active",
            [],
        )->assertStatus(422);

        $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active",
            [
                'incomes' => [['description' => '', 'amount' => -100]],
                'deductions' => 'not-an-array',
            ],
        )->assertStatus(422);
    }
}
