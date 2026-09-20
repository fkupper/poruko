<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Delete a recurring blueprint the authenticated user is allowed to delete.')]
#[IsDestructive]
class DeleteRecurringBlueprintTool extends PorukoTool
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
            'blueprint_id' => $schema->integer()->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $request->validate([
            'blueprint_id' => ['required', 'integer'],
        ]);

        $blueprint = RecurringTransaction::query()
            ->forLedger($ledger->id)
            ->findOrFail((int) $validated['blueprint_id']);

        if ($user->cannot('delete', $blueprint)) {
            abort(403, 'You cannot delete this recurring blueprint.');
        }

        $blueprint->delete();

        return [
            'deleted' => true,
            'id' => (int) $validated['blueprint_id'],
        ];
    }
}
