<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\DeleteTransactionAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Delete a posted transaction the authenticated user is allowed to delete. Settled transactions cannot be deleted.')]
#[IsDestructive]
class DeleteTransactionTool extends PorukoTool
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
            'transaction_id' => $schema->integer()->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
        ]);

        $transaction = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->findOrFail((int) $validated['transaction_id']);

        if ($user->cannot('delete', $transaction)) {
            abort(403, 'You cannot delete this transaction.');
        }

        app(DeleteTransactionAction::class)->execute($transaction);

        return [
            'deleted' => true,
            'id' => (int) $validated['transaction_id'],
        ];
    }
}
