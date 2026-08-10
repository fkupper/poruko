<?php

namespace Tests\Feature\Api;

use App\Enums\SettlementMode;
use App\Http\Controllers\Api\LedgerController;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledgers')]
#[CoversClass(LedgerController::class)]
class LedgerApiTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthenticatedUserCanListTheirLedgers(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ledgerA = Ledger::factory()->create(['name' => 'Home Ledger']);
        $ledgerB = Ledger::factory()->create(['name' => 'Trip Ledger']);
        $foreignLedger = Ledger::factory()->create(['name' => 'Foreign Ledger']);

        $ledgerA->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledgerA->id);
        $user->assignRole('Admin');
        $ledgerB->users()->attach($user->id, ['role' => 'member']);
        setPermissionsTeamId($ledgerB->id);
        $user->assignRole('Member');
        $foreignLedger->users()->attach($otherUser->id, ['role' => 'admin']);
        setPermissionsTeamId($foreignLedger->id);
        $otherUser->assignRole('Admin');

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/ledgers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $ledgerA->id)
            ->assertJsonPath('data.0.name', $ledgerA->name)
            ->assertJsonPath('data.0.settlement_mode', $ledgerA->settlement_mode)
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.1.id', $ledgerB->id)
            ->assertJsonPath('data.1.name', $ledgerB->name)
            ->assertJsonPath('data.1.settlement_mode', $ledgerB->settlement_mode)
            ->assertJsonPath('data.1.users_count', 1);
    }

    public function testAuthenticatedUserWithNoLedgersGetsEmptyList(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/ledgers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testUnauthenticatedUserCannotListLedgers(): void
    {
        $this->getJson('/api/ledgers')
            ->assertUnauthorized();
    }

    public function testAuthenticatedUserCanCreateLedger(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/ledgers', [
            'name' => 'New Household',
            'currency' => 'USD',
            'settlement_mode' => SettlementMode::JointClearinghouse->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Household')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.currency_symbol', '$')
            ->assertJsonPath('data.settlement_mode', SettlementMode::JointClearinghouse->value);

        $this->assertDatabaseHas('ledgers', [
            'name' => 'New Household',
            'currency' => 'USD',
            'settlement_mode' => SettlementMode::JointClearinghouse->value,
        ]);
    }

    public function testCreateLedgerValidationFailsWithoutName(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/ledgers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function testCreateLedgerValidationFailsWithInvalidCurrency(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/ledgers', [
            'name' => 'Invalid Space',
            'currency' => 'INVALID_CODE',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    }

    public function testAdminCanUpdateLedgerSettings(): void
    {
        // Arrange
        [$ledger, $admin] = $this->seedLedgerWithAdminAndMember();

        Sanctum::actingAs($admin, ['*']);

        // Act
        $response = $this->putJson(route('ledgers.settings.update', $ledger), [
            'name' => 'Renamed Ledger',
            'settlement_cutoff_day' => 15,
        ]);

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Ledger')
            ->assertJsonPath('data.settlement_cutoff_day', 15);

        $this->assertDatabaseHas('ledgers', [
            'id' => $ledger->id,
            'name' => 'Renamed Ledger',
            'settlement_cutoff_day' => 15,
        ]);
    }

    public function testUserWithSettingsButWithoutSettlementsCannotUpdateSettlementFields(): void
    {
        // Arrange
        $user = User::factory()->create(['name' => 'Settings Only']);
        $ledger = Ledger::factory()->create([
            'name' => 'Shared Ledger',
            'settlement_cutoff_day' => 1,
        ]);

        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->givePermissionTo('settings');

        Sanctum::actingAs($user, ['*']);

        // Act & Assert — name-only update is allowed
        $this->putJson(route('ledgers.settings.update', $ledger), [
            'name' => 'Renamed By Settings',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed By Settings');

        // Act & Assert — settlement fields require manageSettlements
        $this->putJson(route('ledgers.settings.update', $ledger), [
            'settlement_cutoff_day' => 15,
        ])->assertForbidden();

        $this->assertDatabaseHas('ledgers', [
            'id' => $ledger->id,
            'name' => 'Renamed By Settings',
            'settlement_cutoff_day' => 1,
        ]);
    }

    public function testMemberWithoutSettingsPermissionCannotUpdateLedgerSettings(): void
    {
        // Arrange
        [$ledger, $admin, $member] = $this->seedLedgerWithAdminAndMember();

        Sanctum::actingAs($member, ['*']);

        // Act & Assert
        $this->putJson(route('ledgers.settings.update', $ledger), [
            'name' => 'Nope',
        ])->assertForbidden();
    }

    public function testNonMemberCannotUpdateLedgerSettings(): void
    {
        // Arrange
        [$ledger] = $this->seedLedgerWithAdminAndMember();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider, ['*']);

        // Act & Assert
        $this->putJson(route('ledgers.settings.update', $ledger), [
            'name' => 'Nope',
        ])->assertForbidden();
    }

    /*
     * Seeders.
     */

    /**
     * @return array{0: Ledger, 1: User, 2: User}
     */
    private function seedLedgerWithAdminAndMember(): array
    {
        $admin = User::factory()->create(['name' => 'Admin']);
        $member = User::factory()->create(['name' => 'Member']);
        $ledger = Ledger::factory()->create(['name' => 'Shared Ledger']);

        $ledger->users()->attach($admin->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $admin->assignRole('Admin');

        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');

        return [$ledger, $admin, $member];
    }
}
