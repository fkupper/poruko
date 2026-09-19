<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\PorukoLedgerServer;
use App\Mcp\Tools\GetFinancialProfileTool;
use App\Mcp\Tools\UpdateFinancialProfileTool;
use App\Models\FinancialProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
class McpFinancialProfileToolsTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testCanUpdateOwnProfileAndNotPeer(): void
    {
        [$ledger, $user, , , $peer] = $this->createMcpLedger();

        PorukoLedgerServer::actingAs($user)->tool(UpdateFinancialProfileTool::class, [
            'ledger_id' => $ledger->id,
            'incomes' => [['description' => 'Salary', 'amount' => 400000]],
            'deductions' => [],
        ])->assertOk()->assertSee('Salary');

        $this->assertDatabaseHas('financial_profiles', [
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
        ]);

        PorukoLedgerServer::actingAs($peer)->tool(UpdateFinancialProfileTool::class, [
            'ledger_id' => $ledger->id,
            'incomes' => [['description' => 'Hijack', 'amount' => 1]],
            'deductions' => [],
        ]);

        $this->assertFalse(
            FinancialProfile::query()
                ->where('ledger_id', $ledger->id)
                ->where('user_id', $user->id)
                ->where('incomes', 'like', '%Hijack%')
                ->exists(),
        );

        PorukoLedgerServer::actingAs($user)->tool(GetFinancialProfileTool::class, [
            'ledger_id' => $ledger->id,
            'user_id' => $peer->id,
        ])->assertOk();
    }
}
