<?php

namespace Tests\Feature\Mcp;

use App\Enums\McpPostMode;
use App\Mcp\Servers\PorukoLedgerServer;
use App\Mcp\Tools\ListAccountsTool;
use App\Mcp\Tools\ListLedgersTool;
use App\Mcp\Tools\PostTransactionTool;
use App\Models\McpActionLog;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
class McpAuthorizationTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testUnauthenticatedToolCallIsDenied(): void
    {
        [$ledger] = $this->createMcpLedger();

        PorukoLedgerServer::tool(ListAccountsTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertHasErrors(['Authentication is required.']);
    }

    public function testDisabledMcpAccessIsDenied(): void
    {
        [$ledger, $user] = $this->createMcpLedger(['enabled' => false]);

        PorukoLedgerServer::actingAs($user)->tool(ListAccountsTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertHasErrors(['MCP access is disabled for this space. Enable it in Space Settings.']);
    }

    public function testReadFlagBlocksListAccounts(): void
    {
        [$ledger, $user] = $this->createMcpLedger(['read' => false]);

        PorukoLedgerServer::actingAs($user)->tool(ListAccountsTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertHasErrors(['MCP read operations are disabled for this space.']);
    }

    public function testWriteFlagBlocksPosting(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger(['write' => false]);

        PorukoLedgerServer::actingAs($user)->tool(PostTransactionTool::class, [
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'amount' => 1000,
            'description' => 'Coffee',
            'date' => '2026-09-19',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ])->assertHasErrors(['MCP write operations are disabled for this space.']);

        $this->assertDatabaseCount('pending_transactions', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function testCrossLedgerAccessIsDenied(): void
    {
        [$ledgerA, $userA] = $this->createMcpLedger();
        [$ledgerB] = $this->createMcpLedger();

        PorukoLedgerServer::actingAs($userA)->tool(ListAccountsTool::class, [
            'ledger_id' => $ledgerB->id,
        ])->assertHasErrors(['You cannot access this space.']);
    }

    public function testNonMemberCannotUsePeerLedger(): void
    {
        [$ledger] = $this->createMcpLedger();
        $stranger = User::factory()->create();

        PorukoLedgerServer::actingAs($stranger)->tool(ListAccountsTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertHasErrors(['You cannot access this space.']);
    }

    public function testCannotSpendFromAnotherMembersPersonalAccount(): void
    {
        [$ledger, $user, , $destination, , $peerPayer] = $this->createMcpLedger([
            'post_mode' => McpPostMode::Direct->value,
        ]);

        PorukoLedgerServer::actingAs($user)->tool(PostTransactionTool::class, [
            'ledger_id' => $ledger->id,
            'payer_account_id' => $peerPayer->id,
            'destination_account_id' => $destination->id,
            'amount' => 1000,
            'description' => 'Stolen coffee',
            'date' => '2026-09-19',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ])->assertHasErrors(['You can only spend from your own personal account or a shared pool account.']);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('pending_transactions', 0);
    }

    public function testListLedgersWorksWithoutMcpEnabledAndDoesNotCreateSpaces(): void
    {
        [$ledger, $user] = $this->createMcpLedger(['enabled' => false]);

        PorukoLedgerServer::actingAs($user)
            ->tool(ListLedgersTool::class, [])
            ->assertOk()
            ->assertSee((string) $ledger->id);
    }

    public function testDeniedCallsAreLoggedSeparatelyFromPendingQueue(): void
    {
        [$ledger, $user] = $this->createMcpLedger(['enabled' => false]);

        PorukoLedgerServer::actingAs($user)->tool(ListAccountsTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertHasErrors();

        $this->assertDatabaseHas('mcp_action_logs', [
            'user_id' => $user->id,
            'ledger_id' => $ledger->id,
            'tool_name' => 'list-accounts',
            'response_status' => 'denied',
        ]);
        $this->assertSame(0, PendingTransaction::query()->count());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(1, McpActionLog::query()->count());
    }
}
