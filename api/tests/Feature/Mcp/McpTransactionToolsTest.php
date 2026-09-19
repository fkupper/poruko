<?php

namespace Tests\Feature\Mcp;

use App\Enums\McpPostMode;
use App\Enums\PendingTransactionStatus;
use App\Enums\TransactionSource;
use App\Mcp\Servers\PorukoLedgerServer;
use App\Mcp\Tools\ApprovePendingTransactionsTool;
use App\Mcp\Tools\DeleteTransactionTool;
use App\Mcp\Tools\ListPendingTransactionsTool;
use App\Mcp\Tools\PostTransactionTool;
use App\Mcp\Tools\ProposeTransactionTool;
use App\Mcp\Tools\RenderPendingApprovalsUiTool;
use App\Mcp\Tools\RenderTransactionInspectionUiTool;
use App\Mcp\Tools\ShowTransactionTool;
use App\Models\McpActionLog;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
class McpTransactionToolsTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testProposeCreatesPendingMcpSourceWithoutPostings(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger();

        PorukoLedgerServer::actingAs($user)->tool(ProposeTransactionTool::class, [
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'amount' => 2500,
            'description' => 'Groceries',
            'date' => '2026-09-19',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ])->assertOk()->assertSee('approval_queue');

        $this->assertDatabaseCount('pending_transactions', 1);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('pending_transactions', [
            'ledger_id' => $ledger->id,
            'source' => TransactionSource::Mcp->value,
            'status' => PendingTransactionStatus::Pending->value,
            'suggested_amount' => 2500,
        ]);
        $this->assertDatabaseHas('mcp_action_logs', [
            'tool_name' => 'propose-transaction',
            'response_status' => 'ok',
        ]);
    }

    public function testPostModeQueueDoesNotPostDirectly(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger([
            'post_mode' => McpPostMode::ApprovalQueue->value,
        ]);

        PorukoLedgerServer::actingAs($user)->tool(PostTransactionTool::class, [
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'amount' => 1800,
            'description' => 'Transit',
            'date' => '2026-09-19',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ])->assertOk()->assertSee('approval_queue');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('pending_transactions', 1);
    }

    public function testDirectPostModeCreatesPostedTransactionWithMcpSource(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger([
            'post_mode' => McpPostMode::Direct->value,
        ]);

        PorukoLedgerServer::actingAs($user)->tool(PostTransactionTool::class, [
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'amount' => 1800,
            'description' => 'Transit',
            'date' => '2026-09-19',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ])->assertOk()->assertSee('direct');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'ledger_id' => $ledger->id,
            'source' => TransactionSource::Mcp->value,
            'amount' => 1800,
        ]);
        $this->assertSame(4, Transaction::query()->firstOrFail()->postings()->count());
    }

    public function testApproveRequiresDestructiveCapability(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger(['destructive' => false]);
        $pending = PendingTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'suggested_amount' => 1000,
            'suggested_split_rule' => 'equal',
            'suggested_participants' => [['user_id' => $user->id]],
            'date' => '2026-09-19',
            'source' => TransactionSource::Mcp,
        ]);

        PorukoLedgerServer::actingAs($user)->tool(ApprovePendingTransactionsTool::class, [
            'ledger_id' => $ledger->id,
            'pending_transaction_ids' => [$pending->id],
        ])->assertHasErrors(['MCP destructive operations are disabled for this space.']);
    }

    public function testApprovePendingThenShowTransactionAndA2ui(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger();
        $pending = PendingTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'suggested_description' => 'MCP groceries',
            'suggested_amount' => 2500,
            'suggested_split_rule' => 'equal',
            'suggested_participants' => [['user_id' => $user->id]],
            'date' => '2026-09-19',
            'source' => TransactionSource::Mcp,
        ]);

        PorukoLedgerServer::actingAs($user)->tool(ListPendingTransactionsTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertOk()->assertSee('MCP groceries');

        PorukoLedgerServer::actingAs($user)->tool(ApprovePendingTransactionsTool::class, [
            'ledger_id' => $ledger->id,
            'pending_transaction_ids' => [$pending->id],
        ])->assertOk();

        $transaction = Transaction::query()->firstOrFail();

        PorukoLedgerServer::actingAs($user)->tool(ShowTransactionTool::class, [
            'ledger_id' => $ledger->id,
            'transaction_id' => $transaction->id,
        ])->assertOk()->assertSee(TransactionSource::Mcp->value);

        PorukoLedgerServer::actingAs($user)->tool(RenderTransactionInspectionUiTool::class, [
            'ledger_id' => $ledger->id,
            'transaction_id' => $transaction->id,
        ])->assertOk()->assertSee('poruko.transaction-inspection');

        PorukoLedgerServer::actingAs($user)->tool(RenderPendingApprovalsUiTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertOk()->assertSee('poruko.pending-approvals');
    }

    public function testDeleteRequiresDestructiveAndDoesNotUseActionLogAsQueue(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger([
            'post_mode' => McpPostMode::Direct->value,
        ]);

        PorukoLedgerServer::actingAs($user)->tool(PostTransactionTool::class, [
            'ledger_id' => $ledger->id,
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'amount' => 900,
            'description' => 'Snack',
            'date' => '2026-09-19',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ])->assertOk();

        $transactionId = Transaction::query()->value('id');

        PorukoLedgerServer::actingAs($user)->tool(DeleteTransactionTool::class, [
            'ledger_id' => $ledger->id,
            'transaction_id' => $transactionId,
        ])->assertOk()->assertSee('deleted');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertGreaterThan(0, McpActionLog::query()->count());
        $this->assertSame(0, PendingTransaction::query()->count());
    }
}
