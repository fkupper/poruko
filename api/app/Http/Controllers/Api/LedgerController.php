<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLedgerRequest;
use App\Http\Requests\UpdateLedgerSettingsRequest;
use App\Http\Resources\LedgerResource;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\CreateLedgerAction;
use App\Modules\Ledger\Data\CreateLedgerData;
use App\Modules\Ledger\Queries\UserLedgersIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LedgerController extends Controller
{
    public function index(Request $request, UserLedgersIndexQuery $query): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return LedgerResource::collection(
            $query->execute($user),
        );
    }

    public function store(StoreLedgerRequest $request, CreateLedgerAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ledger = $action->execute(
            user: $user,
            data: CreateLedgerData::fromArray($request->validated()),
        );

        return LedgerResource::make($ledger)
            ->response()
            ->setStatusCode(201);
    }

    public function updateSettings(UpdateLedgerSettingsRequest $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validated();

        $ledger->update(array_filter([
            'name' => $validated['name'] ?? null,
            'currency' => $validated['currency_code'] ?? null,
            'settlement_cutoff_day' => $validated['settlement_cutoff_day'] ?? null,
            'settlement_timezone' => $validated['settlement_timezone'] ?? null,
            'settlement_cutoff_time' => $validated['settlement_cutoff_time'] ?? null,
            'settlement_auto_execute_enabled' => $validated['settlement_auto_execute_enabled'] ?? null,
        ], fn ($value) => !is_null($value)));

        return LedgerResource::make($ledger)->response();
    }
}
