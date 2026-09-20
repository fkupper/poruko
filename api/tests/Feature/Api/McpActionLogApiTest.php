<?php

namespace Tests\Feature\Api;

use App\Enums\McpOperation;
use App\Http\Controllers\Api\McpActionLogController;
use App\Models\McpActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
#[CoversClass(McpActionLogController::class)]
class McpActionLogApiTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testMemberSeesOwnLogsAndAdminSeesAll(): void
    {
        [$ledger, $admin, , , $peer] = $this->createMcpLedger();

        McpActionLog::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $admin->id,
            'tool_name' => 'list-accounts',
            'operation' => McpOperation::Read,
        ]);
        McpActionLog::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $peer->id,
            'tool_name' => 'list-transactions',
            'operation' => McpOperation::Read,
        ]);

        Sanctum::actingAs($peer, ['*']);
        $this->getJson("/api/ledgers/{$ledger->id}/mcp-action-logs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $peer->id);

        Sanctum::actingAs($admin, ['*']);
        $this->getJson("/api/ledgers/{$ledger->id}/mcp-action-logs")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function testNonMemberCannotListLogs(): void
    {
        [$ledger] = $this->createMcpLedger();
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/mcp-action-logs")->assertForbidden();
    }
}
