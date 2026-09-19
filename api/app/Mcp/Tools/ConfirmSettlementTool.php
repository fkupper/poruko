<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Http\Resources\SettlementResource;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\ConfirmSettlementAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Manually confirm and execute a settlement cycle. Requires settlement permission and destructive MCP access. Does not expose space settings CRUD.')]
#[IsDestructive]
class ConfirmSettlementTool extends PorukoTool
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
            'period_end' => $schema->string()->description('Period end date (YYYY-MM-DD).')->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        if ($user->cannot('manageSettlements', $ledger)) {
            abort(403, 'You cannot confirm settlements for this space.');
        }

        $validated = $request->validate([
            'period_end' => ['required', 'date'],
        ]);

        $settlement = app(ConfirmSettlementAction::class)->execute($ledger, (string) $validated['period_end']);

        return [
            'data' => $this->resourceArray(SettlementResource::make(
                $settlement->load(['transactions.payerAccount']),
            )),
        ];
    }
}
