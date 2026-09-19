<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Enums\McpPostMode;
use App\Http\Resources\McpSettingsResource;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Mcp\Exceptions\McpAuthorizationException;
use App\Modules\Mcp\Services\McpCapabilityService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update the authenticated user\'s MCP settings for this space. This is not user-management or space CRUD.')]
class UpdateMcpSettingsTool extends PorukoTool
{
    protected function operation(): McpOperation
    {
        return McpOperation::Write;
    }

    protected function requiresMcpEnabled(): bool
    {
        return false;
    }

    protected function requiresCapability(): bool
    {
        return false;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->ledgerIdSchema($schema),
            'enabled' => $schema->boolean()->required(),
            'allow_read' => $schema->boolean()->required(),
            'allow_write' => $schema->boolean()->required(),
            'allow_destructive' => $schema->boolean()->required(),
            'post_mode' => $schema->string()->enum(['direct', 'approval_queue'])->required(),
            'destructive_ack' => $schema->boolean()->description('Required when enabling destructive operations.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'allow_read' => ['required', 'boolean'],
            'allow_write' => ['required', 'boolean'],
            'allow_destructive' => ['required', 'boolean'],
            'post_mode' => ['required', 'in:direct,approval_queue'],
            'destructive_ack' => ['nullable', 'boolean'],
        ]);

        if ($validated['allow_destructive'] && empty($validated['destructive_ack'])) {
            throw new McpAuthorizationException(
                'You must acknowledge the risks to enable destructive MCP operations.',
            );
        }

        $membership = app(McpCapabilityService::class)->membership($user, $ledger);
        $membership->update([
            'mcp_enabled' => (bool) $validated['enabled'],
            'mcp_allow_read' => (bool) $validated['allow_read'],
            'mcp_allow_write' => (bool) $validated['allow_write'],
            'mcp_allow_destructive' => (bool) $validated['allow_destructive'],
            'mcp_post_mode' => McpPostMode::from((string) $validated['post_mode']),
        ]);

        return [
            'data' => $this->resourceArray(McpSettingsResource::make($membership->refresh())),
        ];
    }
}
