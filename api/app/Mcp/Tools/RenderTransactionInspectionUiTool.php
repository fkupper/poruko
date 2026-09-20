<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\A2UiSurface;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Return an A2UI surface for inspecting a posted transaction, including source provenance.')]
#[IsReadOnly]
class RenderTransactionInspectionUiTool extends PorukoTool
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
            ->with(['payerAccount', 'destinationAccount', 'postings'])
            ->findOrFail((int) $validated['transaction_id']);

        if ($user->cannot('view', $transaction)) {
            abort(403, 'You cannot view this transaction.');
        }

        return A2UiSurface::transactionInspection($transaction);
    }
}
