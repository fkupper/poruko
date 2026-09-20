<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Queries\UserLedgersIndexQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List spaces the authenticated user belongs to. Does not create, update, or delete spaces.')]
#[IsReadOnly]
class ListLedgersTool extends PorukoTool
{
    protected function operation(): McpOperation
    {
        return McpOperation::Read;
    }

    protected function requiresLedger(): bool
    {
        return false;
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
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        return [
            'data' => $this->resourceCollection(app(UserLedgersIndexQuery::class)->execute($user)),
        ];
    }
}
