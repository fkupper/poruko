<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Http\Resources\McpSettingsResource;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Mcp\Services\McpCapabilityService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the authenticated user\'s MCP operation settings for a space: enabled, read, write, destructive, and post_mode.')]
#[IsReadOnly]
class GetMcpSettingsTool extends PorukoTool
{
    protected function operation(): McpOperation
    {
        return McpOperation::Read;
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
        return $this->ledgerIdSchema($schema);
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $membership = app(McpCapabilityService::class)->membership($user, $ledger);

        return [
            'data' => $this->resourceArray(McpSettingsResource::make($membership)),
        ];
    }
}
