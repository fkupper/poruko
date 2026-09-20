<?php

namespace Tests\Feature\Api;

use App\Enums\McpPostMode;
use App\Http\Controllers\Api\McpSettingsController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
#[CoversClass(McpSettingsController::class)]
class McpSettingsApiTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testMemberCanReadAndUpdateOwnMcpSettings(): void
    {
        [$ledger, $user] = $this->createMcpLedger(['enabled' => false, 'write' => false, 'destructive' => false]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/mcp-settings")
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.allow_read', true)
            ->assertJsonPath('data.post_mode', McpPostMode::ApprovalQueue->value);

        $this->putJson("/api/ledgers/{$ledger->id}/mcp-settings", [
            'enabled' => true,
            'allow_read' => true,
            'allow_write' => true,
            'allow_destructive' => true,
            'post_mode' => McpPostMode::Direct->value,
            'destructive_ack' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.allow_write', true)
            ->assertJsonPath('data.allow_destructive', true)
            ->assertJsonPath('data.post_mode', McpPostMode::Direct->value);
    }

    public function testDestructiveRequiresAcknowledgement(): void
    {
        [$ledger, $user] = $this->createMcpLedger();
        Sanctum::actingAs($user, ['*']);

        $this->putJson("/api/ledgers/{$ledger->id}/mcp-settings", [
            'enabled' => true,
            'allow_read' => true,
            'allow_write' => false,
            'allow_destructive' => true,
            'post_mode' => McpPostMode::ApprovalQueue->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['destructive_ack']);
    }

    public function testNonMemberCannotReadMcpSettings(): void
    {
        [$ledger] = $this->createMcpLedger();
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger, ['*']);

        $this->getJson("/api/ledgers/{$ledger->id}/mcp-settings")->assertForbidden();
    }
}
