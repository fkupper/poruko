<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\McpA2uiController;
use App\Models\PendingTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
#[CoversClass(McpA2uiController::class)]
class McpA2uiApiTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testMemberCanRenderOfficialA2uiPendingApprovalSurface(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger();
        PendingTransaction::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'suggested_description' => 'Agent groceries',
            'suggested_amount' => 4250,
            'source' => 'mcp',
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/mcp-a2ui/pending-approvals")
            ->assertOk()
            ->assertJsonPath('data.protocol', 'a2ui')
            ->assertJsonPath('data.version', 'v0.9')
            ->assertJsonPath('data.surface_id', 'poruko.pending-approvals')
            ->assertJsonPath('data.messages.0.createSurface.catalogId', 'https://a2ui.org/specification/v0_9/catalogs/basic/catalog.json')
            ->assertJsonPath('data.messages.1.updateComponents.components.0.id', 'root')
            ->assertJsonFragment(['text' => 'Agent groceries — 42.50']);

        $this->assertDatabaseHas('mcp_action_logs', [
            'user_id' => $user->id,
            'ledger_id' => $ledger->id,
            'tool_name' => 'render-pending-approvals-ui',
            'operation' => 'read',
            'response_status' => 'ok',
        ]);
    }

    public function testReadSettingAndMembershipProtectA2uiSurface(): void
    {
        [$ledger, $user] = $this->createMcpLedger(['read' => false]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/mcp-a2ui/pending-approvals")
            ->assertForbidden()
            ->assertJsonPath('message', 'MCP read operations are disabled for this space.');

        $this->assertDatabaseHas('mcp_action_logs', [
            'user_id' => $user->id,
            'tool_name' => 'render-pending-approvals-ui',
            'response_status' => 'denied',
        ]);

        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->getJson("/api/ledgers/{$ledger->id}/mcp-a2ui/pending-approvals")
            ->assertForbidden();
    }

    public function testUnauthenticatedUserCannotRenderA2uiSurface(): void
    {
        [$ledger] = $this->createMcpLedger();

        $this->getJson("/api/ledgers/{$ledger->id}/mcp-a2ui/pending-approvals")
            ->assertUnauthorized();
    }
}
