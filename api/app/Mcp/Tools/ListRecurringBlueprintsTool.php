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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List recurring blueprints in the space.')]
#[IsReadOnly]
class ListRecurringBlueprintsTool extends PorukoTool
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
            'status' => $schema->string()->enum(['active', 'all'])->default('active'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        if ($user->cannot('viewAny', RecurringTransaction::class)) {
            abort(403, 'You cannot view recurring blueprints.');
        }

        $status = (string) $request->get('status', 'active');
        $query = RecurringTransaction::query()
            ->forLedger($ledger->id)
            ->with(['payerAccount', 'destinationAccount'])
            ->orderByDesc('valid_from');

        if ($status === 'all') {
            $query->withTrashed();
        } else {
            $query->active();
        }

        return [
            'data' => $this->resourceCollection($query->get()),
        ];
    }
}
