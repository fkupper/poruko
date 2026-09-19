<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\User;
use App\Modules\Ledger\Actions\ApprovePendingTransactionsAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Approve one or more pending proposals. This commits ledger postings and is a destructive MCP operation. The MCP action log is not used as the approval queue.')]
#[IsDestructive]
class ApprovePendingTransactionsTool extends PorukoTool
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
            'pending_transaction_ids' => $schema->array()->description('Pending transaction ids to approve.')->required(),
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
        ]);

        /** @var list<int> $ids */
        $ids = array_map('intval', $validated['pending_transaction_ids']);

        foreach ($ids as $id) {
            $pending = PendingTransaction::query()
                ->where('ledger_id', $ledger->id)
                ->findOrFail($id);

            if ($user->cannot('approve', $pending)) {
                abort(403, 'You cannot approve this pending transaction.');
            }
        }

        $transactions = app(ApprovePendingTransactionsAction::class)->execute($ledger, $user, $ids);
        $transactions->each(
            fn ($transaction) => $transaction->load(['payerAccount', 'destinationAccount', 'postings']),
        );

        return [
            'data' => $this->resourceCollection($transactions),
        ];
    }
}
