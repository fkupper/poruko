<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\A2UiSurface;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\User;
use App\Modules\Ledger\Queries\PendingTransactionIndexQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Return an A2UI surface for the shared pending-approval queue. Uses the same pending resources as the human UI. This is not the MCP action log.')]
#[IsReadOnly]
class RenderPendingApprovalsUiTool extends PorukoTool
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

        return A2UiSurface::pendingApprovals(app(PendingTransactionIndexQuery::class)->execute($ledger));
    }
}
