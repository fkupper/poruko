<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Queries\LedgerAccountIndexQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List accounts in a space. Personal accounts owned by other members are visible but cannot be mutated.')]
#[IsReadOnly]
class ListAccountsTool extends PorukoTool
{
    protected function operation(): McpOperation
    {
        return McpOperation::Read;
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

        return [
            'data' => $this->resourceCollection(app(LedgerAccountIndexQuery::class)->get($ledger)),
        ];
    }
}
