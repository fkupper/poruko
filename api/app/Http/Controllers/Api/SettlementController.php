<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmSettlementCycleRequest;
use App\Http\Requests\PreviewSettlementRequest;
use App\Http\Requests\UpdateLedgerCycleConfigRequest;
use App\Http\Resources\LedgerResource;
use App\Http\Resources\SettlementResource;
use App\Models\Ledger;
use App\Modules\Ledger\Actions\ConfirmSettlementAction;
use App\Modules\Ledger\Actions\PreviewSettlementAction;
use App\Modules\Ledger\Queries\SettlementIndexQuery;
use App\Modules\Ledger\Services\SettlementSafetyGateService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SettlementController extends Controller
{
    public function __construct(
        private readonly SettlementSafetyGateService $settlementSafetyGateService,
    ) {}

    public function preview(PreviewSettlementRequest $request, Ledger $ledger, PreviewSettlementAction $action): Response
    {
        $date = (string) $request->validated()['date'];
        $preview = $action->execute($ledger, $date);
        $gate = $this->settlementSafetyGateService->evaluate($ledger, $preview);

        return response([
            'data' => [
                ...$preview,
                'safety_gate' => [
                    'auto_allowed' => $gate['auto_allowed'],
                    'reason' => $gate['reason'],
                ],
            ],
        ]);
    }

    public function index(Ledger $ledger, SettlementIndexQuery $query): AnonymousResourceCollection
    {
        return SettlementResource::collection($query->execute($ledger));
    }

    public function updateCycleConfig(UpdateLedgerCycleConfigRequest $request, Ledger $ledger): LedgerResource
    {
        $ledger->update($request->validated());

        return new LedgerResource($ledger->fresh());
    }

    public function confirm(
        ConfirmSettlementCycleRequest $request,
        Ledger $ledger,
        string $cycle,
        ConfirmSettlementAction $action,
    ): SettlementResource {
        $data = $request->validated();
        $periodEnd = (string) ($data['period_end'] ?? $cycle);

        return new SettlementResource($action->execute($ledger, $periodEnd));
    }
}
