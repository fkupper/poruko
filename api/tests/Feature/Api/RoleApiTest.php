<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\RoleController;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

#[Group('roles')]
#[CoversClass(RoleController::class)]
class RoleApiTest extends TestCase
{
    use RefreshDatabase;

    public function testRolesFromAnotherLedgerDoNotAppear(): void
    {
        // Arrange
        [$ledger, $admin] = $this->seedLedgerWithAdmin();
        $otherLedger = Ledger::factory()->create();

        Role::query()->create([
            'name' => 'OtherLedgerOnlyRole',
            'guard_name' => 'web',
            'team_id' => $otherLedger->id,
        ]);

        Sanctum::actingAs($admin, ['*']);

        // Act
        $response = $this->getJson(route('ledgers.roles.index', ['ledger' => $ledger->id]));

        // Assert
        $response->assertOk();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Admin'));
        $this->assertTrue($names->contains('Member'));
        $this->assertFalse($names->contains('OtherLedgerOnlyRole'));
    }

    /*
     * Seeders.
     */

    /**
     * @return array{0: Ledger, 1: User}
     */
    private function seedLedgerWithAdmin(): array
    {
        $admin = User::factory()->create();
        $ledger = Ledger::factory()->create();

        $ledger->users()->attach($admin->id, ['role' => 'admin']);
        setPermissionsTeamId($ledger->id);
        $admin->assignRole('Admin');

        return [$ledger, $admin];
    }
}
