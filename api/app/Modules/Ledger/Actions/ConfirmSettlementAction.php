<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Ledger;
use App\Models\Settlement;
use App\Modules\Ledger\Services\SettlementCycleService;
use App\Modules\Ledger\Services\SettlementSafetyGateService;
use Illuminate\Validation\ValidationException;

final readonly class ConfirmSettlementAction
{
    public function __construct(
        private readonly PreviewSettlementAction $previewSettlementAction,
        private readonly ExecuteSettlementAction $executeSettlementAction,
        private readonly SettlementSafetyGateService $settlementSafetyGateService,
        private readonly SettlementCycleService $settlementCycleService,
    ) {}

    public function execute(Ledger $ledger, string $periodEnd): Settlement
    {
        $preview = $this->previewSettlementAction->executeForPeriodEnd($ledger, $periodEnd);
        $gate = $this->settlementSafetyGateService->evaluate($ledger, $preview);

        if ($gate['auto_allowed']) {
            throw ValidationException::withMessages([
                'period_end' => ['This cycle does not require manual confirmation.'],
            ]);
        }

        $period = $this->settlementCycleService->resolvePeriodForPeriodEnd($ledger, $periodEnd);
        Settlement::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'period_start' => $period['period_start'],
                'period_end' => $period['period_end'],
            ],
            [
                'executed_at' => null,
            ],
        );

        return $this->executeSettlementAction->execute($ledger, $periodEnd);
    }
}
