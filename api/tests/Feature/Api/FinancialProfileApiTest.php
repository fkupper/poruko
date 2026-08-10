<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\FinancialProfileController;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('financial-profile')]
#[CoversClass(FinancialProfileController::class)]
class FinancialProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function testMemberCanUpdateActiveFinancialProfile(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

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

    public function testUpdateIsIdempotentForExistingActiveProfile(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

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

    public function testMemberCanShowActiveFinancialProfile(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

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

    public function testShowReturnsNotFoundWhenNoActiveProfileExists(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        Sanctum::actingAs($user);

        $this->getJson("/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active")
            ->assertNotFound();
    }

    public function testNonMemberCannotUpdateFinancialProfile(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($target->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $target->assignRole('Member');

        Sanctum::actingAs($user);

        $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$target->id}/financial-profile/active",
            [
                'incomes' => [['description' => 'Salary', 'amount' => 400000]],
                'deductions' => [],
            ],
        )->assertForbidden();
    }

    public function testUnauthenticatedUserCannotAccessFinancialProfile(): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

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

    public function testMemberCannotUpdateAnotherMembersFinancialProfile(): void
    {
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($memberA->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $memberA->assignRole('Member');
        $ledger->users()->attach($memberB->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $memberB->assignRole('Member');

        Sanctum::actingAs($memberA);

        $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$memberB->id}/financial-profile/active",
            [
                'incomes' => [['description' => 'Salary', 'amount' => 400000]],
                'deductions' => [],
            ],
        )->assertForbidden();
    }

    public function testNonMemberCannotViewFinancialProfile(): void
    {
        $outsider = User::factory()->create();
        $member = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $member->id,
            'valid_from' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/ledgers/{$ledger->id}/users/{$member->id}/financial-profile/active")
            ->assertForbidden();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $expectedErrors
     */
    #[DataProvider('updateValidationDataProvider')]
    public function testUpdateValidatesRequiredFields(array $payload, array $expectedErrors): void
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/ledgers/{$ledger->id}/users/{$user->id}/financial-profile/active",
            $payload,
        );

        $response->assertStatus(422);

        foreach ($expectedErrors as $error) {
            $response->assertJsonValidationErrors([$error]);
        }
    }

    public static function updateValidationDataProvider(): array
    {
        return [
            'empty payload' => [
                'payload' => [],
                'expectedErrors' => ['incomes', 'deductions'],
            ],
            'empty incomes array' => [
                'payload' => [
                    'incomes' => [],
                    'deductions' => [],
                ],
                'expectedErrors' => ['incomes'],
            ],
            'invalid incomes and deductions' => [
                'payload' => [
                    'incomes' => [['description' => '', 'amount' => -100]],
                    'deductions' => 'not-an-array',
                ],
                'expectedErrors' => ['incomes.0.description', 'incomes.0.amount', 'deductions'],
            ],
            'invalid deductions items' => [
                'payload' => [
                    'incomes' => [['description' => 'Salary', 'amount' => 100000]],
                    'deductions' => [
                        ['description' => '', 'amount' => 5000],
                        ['description' => 'Tax', 'amount' => -100],
                    ],
                ],
                'expectedErrors' => ['deductions.0.description', 'deductions.1.amount'],
            ],
        ];
    }

    public function testMemberCanViewAnotherMembersFinancialProfile(): void
    {
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($memberA->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $memberA->assignRole('Member');
        $ledger->users()->attach($memberB->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $memberB->assignRole('Member');

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $memberB->id,
            'valid_from' => now()->startOfMonth()->format('Y-m-d'),
            'valid_to' => null,
            'incomes' => [['description' => 'Salary', 'amount' => 300000]],
            'deductions' => [],
        ]);

        Sanctum::actingAs($memberA);

        $this->getJson("/api/ledgers/{$ledger->id}/users/{$memberB->id}/financial-profile/active")
            ->assertOk()
            ->assertJsonPath('data.user_id', $memberB->id)
            ->assertJsonPath('data.computed.shareable_income', 300000);
    }
}
