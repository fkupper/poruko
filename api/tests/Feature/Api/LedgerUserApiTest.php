<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\LedgerUserController;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger-users')]
#[CoversClass(LedgerUserController::class)]
class LedgerUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function testMemberCanListLedgerUsersWithShareableIncome(): void
    {
        // Arrange
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($alice->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $alice->assignRole('Admin');
        $ledger->users()->attach($bob->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $bob->assignRole('Member');

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $alice->id,
            'valid_from' => now()->startOfMonth()->format('Y-m-d'),
            'valid_to' => null,
            'incomes' => [['description' => 'Salary', 'amount' => 60000]],
            'deductions' => [],
        ]);

        Sanctum::actingAs($alice, ['*']);

        // Act
        $response = $this->getJson("/api/ledgers/{$ledger->id}/users");

        // Assert
        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $data = $response->json('data');
        $byId = collect($data)->keyBy('id');
        $this->assertSame('Alice', $byId[$alice->id]['name']);
        $this->assertSame(60000, $byId[$alice->id]['shareable_income']);
        $this->assertSame('Bob', $byId[$bob->id]['name']);
        $this->assertSame(0, $byId[$bob->id]['shareable_income']);
    }

    public function testListRespectsDateParameter(): void
    {
        // Arrange
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'incomes' => [['description' => 'Salary', 'amount' => 100000]],
            'deductions' => [],
        ]);

        Sanctum::actingAs($user, ['*']);

        // Act
        $response = $this->getJson("/api/ledgers/{$ledger->id}/users?date=2026-01-15");

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.0.shareable_income', 100000);
    }

    public function testAdminCanDeactivateMemberAndRemoveSpatieRole(): void
    {
        // Arrange
        [$ledger, $admin, $member] = $this->seedLedgerWithAdminAndMember();

        Sanctum::actingAs($admin, ['*']);

        // Act
        $response = $this->deleteJson(route('ledgers.users.destroy', [
            'ledger' => $ledger->id,
            'user' => $member->id,
        ]));

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', 'User has been deactivated.');

        $this->assertFalse($member->fresh()->ledgers()->where('ledgers.id', $ledger->id)->exists());
        $this->assertTrue(
            LedgerUser::onlyTrashed()
                ->where('ledger_id', $ledger->id)
                ->where('user_id', $member->id)
                ->exists()
        );

        setPermissionsTeamId($ledger->id);
        $this->assertFalse($member->fresh()->hasRole('Member'));
    }

    public function testAdminCanRestoreDeactivatedMemberAndReassignSpatieRole(): void
    {
        // Arrange
        [$ledger, $admin, $member] = $this->seedLedgerWithAdminAndMember();

        LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $member->id)
            ->firstOrFail()
            ->delete();

        setPermissionsTeamId($ledger->id);
        $member->removeRole('Member');

        Sanctum::actingAs($admin, ['*']);

        // Act
        $response = $this->postJson(route('ledgers.users.restore', [
            'ledger' => $ledger->id,
            'user' => $member->id,
        ]));

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', 'User has been restored.');

        $this->assertTrue($member->fresh()->ledgers()->where('ledgers.id', $ledger->id)->exists());
        $this->assertFalse(
            LedgerUser::onlyTrashed()
                ->where('ledger_id', $ledger->id)
                ->where('user_id', $member->id)
                ->exists()
        );

        setPermissionsTeamId($ledger->id);
        $this->assertTrue($member->fresh()->hasRole('Member'));
    }

    public function testAdminCannotDeactivateSelf(): void
    {
        // Arrange
        [$ledger, $admin] = $this->seedLedgerWithAdminAndMember();

        Sanctum::actingAs($admin, ['*']);

        // Act
        $response = $this->deleteJson(route('ledgers.users.destroy', [
            'ledger' => $ledger->id,
            'user' => $admin->id,
        ]));

        // Assert
        $response->assertStatus(422);
        $this->assertTrue($admin->fresh()->ledgers()->where('ledgers.id', $ledger->id)->exists());
    }

    public function testAdminResetTwoFactorRevokesUserTokens(): void
    {
        // Arrange
        [$ledger, $admin, $member] = $this->seedLedgerWithAdminAndMember();

        $member->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['aaaa-bbbb'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $plainTextToken = $member->createToken('api-token', ['*'])->plainTextToken;
        $this->assertSame(1, $member->tokens()->count());

        Sanctum::actingAs($admin, ['*']);

        // Act
        $response = $this->deleteJson(route('ledgers.users.two-factor.destroy', [
            'ledger' => $ledger->id,
            'user' => $member->id,
        ]));

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', 'Two-factor authentication has been disabled for this user.');

        $this->assertSame(0, $member->fresh()->tokens()->count());
        $this->assertNull($member->fresh()->two_factor_secret);

        app('auth')->forgetGuards();

        $this->withToken($plainTextToken)
            ->getJson(route('ledgers.index'))
            ->assertUnauthorized();
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
        $ledger = Ledger::factory()->create();

        $ledger->users()->attach($admin->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $admin->assignRole('Admin');

        $ledger->users()->attach($member->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $member->assignRole('Member');

        return [$ledger, $admin, $member];
    }
}
