<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\LedgerUserController;
use App\Models\FinancialProfile;
use App\Models\Ledger;
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
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($alice->id, ['role' => 'admin']);
        $ledger->users()->attach($bob->id, ['role' => 'member']);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $alice->id,
            'valid_from' => now()->startOfMonth()->format('Y-m-d'),
            'valid_to' => null,
            'incomes' => [['description' => 'Salary', 'amount' => 60000]],
            'deductions' => [],
        ]);

        Sanctum::actingAs($alice);

        $response = $this->getJson("/api/ledgers/{$ledger->id}/users");

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
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'incomes' => [['description' => 'Salary', 'amount' => 100000]],
            'deductions' => [],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/ledgers/{$ledger->id}/users?date=2026-01-15");

        $response->assertOk()
            ->assertJsonPath('data.0.shareable_income', 100000);
    }
}
