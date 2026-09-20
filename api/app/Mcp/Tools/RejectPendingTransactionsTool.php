<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\User;
use App\Modules\Ledger\Actions\RejectPendingTransactionsAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Reject one or more pending proposals without creating postings. This is a destructive MCP operation.')]
#[IsDestructive]
class RejectPendingTransactionsTool extends PorukoTool
{
    protected function operation(): McpOperation
    {
        return McpOperation::Destructive;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->ledgerIdSchema($schema),
            'pending_transaction_ids' => $schema->array()->required(),
            'reason' => $schema->string()->description('Optional rejection reason.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $request->validate([
            'pending_transaction_ids' => ['required', 'array', 'min:1'],
            'pending_transaction_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var list<int> $ids */
        $ids = array_map('intval', $validated['pending_transaction_ids']);

        foreach ($ids as $id) {
            $pending = PendingTransaction::query()
                ->where('ledger_id', $ledger->id)
                ->findOrFail($id);

            if ($user->cannot('reject', $pending)) {
                abort(403, 'You cannot reject this pending transaction.');
            }
        }

        $rejected = app(RejectPendingTransactionsAction::class)->execute(
            $ledger,
            $user,
            $ids,
            isset($validated['reason']) ? (string) $validated['reason'] : null,
        );

        $rejected->each(
            fn (PendingTransaction $pending) => $pending->load(['proposer', 'payerAccount', 'destinationAccount']),
        );

        return [
            'data' => $this->resourceCollection($rejected),
        ];
    }
}
