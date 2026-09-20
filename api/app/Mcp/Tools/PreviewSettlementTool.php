<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\PreviewSettlementAction;
use App\Modules\Ledger\Services\SettlementCycleService;
use App\Modules\Ledger\Services\SettlementSafetyGateService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Preview the next or a specific settlement period. Does not confirm or execute settlement.')]
#[IsReadOnly]
class PreviewSettlementTool extends PorukoTool
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
            'date' => $schema->string()->description('Optional date inside the period (YYYY-MM-DD).'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $cycleService = app(SettlementCycleService::class);
        $date = $validated['date'] ?? null;
        $period = $date === null
            ? $cycleService->resolveNextPendingPeriod($ledger)
            : $cycleService->resolvePeriodForDate($ledger, (string) $date);

        $preview = app(PreviewSettlementAction::class)->executeForPeriod($ledger, $period);
        $gate = app(SettlementSafetyGateService::class)->evaluate($ledger, $preview);

        return [
            'data' => [
                ...$preview,
                'safety_gate' => [
                    'auto_allowed' => $gate['auto_allowed'],
                    'reason' => $gate['reason'],
                ],
            ],
        ];
    }
}
