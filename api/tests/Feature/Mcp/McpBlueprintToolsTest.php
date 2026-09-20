<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\PorukoLedgerServer;
use App\Mcp\Tools\CreateRecurringBlueprintTool;
use App\Mcp\Tools\DeleteRecurringBlueprintTool;
use App\Mcp\Tools\ListRecurringBlueprintsTool;
use App\Models\RecurringTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
class McpBlueprintToolsTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testCannotCreateBlueprintFromAnotherMembersPayer(): void
    {
        [$ledger, $user, , $destination, , $peerPayer] = $this->createMcpLedger();

        PorukoLedgerServer::actingAs($user)->tool(CreateRecurringBlueprintTool::class, [
            'ledger_id' => $ledger->id,
            'payer_account_id' => $peerPayer->id,
            'destination_account_id' => $destination->id,
            'amount' => 5000,
            'description' => 'Rent',
            'split_rule' => 'equal',
            'start_date' => '2026-09-01',
            'frequency' => 'monthly',
        ])->assertHasErrors(['You can only spend from your own personal account or a shared pool account.']);

        $this->assertDatabaseCount('recurring_transactions', 0);
    }

    public function testCanCreateListAndDeleteOwnBlueprint(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger();

        PorukoLedgerServer::actingAs($user)->tool(CreateRecurringBlueprintTool::class, [
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'amount' => 5000,
            'description' => 'Internet',
            'split_rule' => 'equal',
            'start_date' => '2026-09-01',
            'frequency' => 'monthly',
        ])->assertOk()->assertSee('Internet');

        $blueprintId = RecurringTransaction::query()->value('id');

        PorukoLedgerServer::actingAs($user)->tool(ListRecurringBlueprintsTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertOk()->assertSee('Internet');

        PorukoLedgerServer::actingAs($user)->tool(DeleteRecurringBlueprintTool::class, [
            'ledger_id' => $ledger->id,
            'blueprint_id' => $blueprintId,
        ])->assertOk()->assertSee('deleted');
    }
}
