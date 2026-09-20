<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\PorukoLedgerServer;
use App\Mcp\Tools\ConfirmSettlementTool;
use App\Mcp\Tools\ListSettlementsTool;
use App\Mcp\Tools\PreviewSettlementTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
class McpSettlementToolsTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testPreviewAndListAreReadable(): void
    {
        [$ledger, $user] = $this->createMcpLedger();

        PorukoLedgerServer::actingAs($user)->tool(PreviewSettlementTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertOk();

        PorukoLedgerServer::actingAs($user)->tool(ListSettlementsTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertOk();
    }

    public function testConfirmRequiresDestructiveFlag(): void
    {
        [$ledger, $user] = $this->createMcpLedger(['destructive' => false]);

        PorukoLedgerServer::actingAs($user)->tool(ConfirmSettlementTool::class, [
            'ledger_id' => $ledger->id,
            'period_end' => '2026-09-30',
        ])->assertHasErrors(['MCP destructive operations are disabled for this space.']);
    }
}
