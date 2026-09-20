<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\User;
use App\Modules\Ledger\Queries\PendingTransactionIndexQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List pending transaction proposals awaiting human approval. This is not the MCP action log.')]
#[IsReadOnly]
class ListPendingTransactionsTool extends PorukoTool
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

        if ($user->cannot('viewAny', [PendingTransaction::class, $ledger])) {
            abort(403, 'You cannot view pending transactions.');
        }

        return [
            'data' => $this->resourceCollection(app(PendingTransactionIndexQuery::class)->execute($ledger)),
        ];
    }
}
