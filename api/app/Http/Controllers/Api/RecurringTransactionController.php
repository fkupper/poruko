<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecurringTransactionRequest;
use App\Http\Requests\UpdateRecurringTransactionRequest;
use App\Http\Resources\RecurringTransactionResource;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Modules\Ledger\Actions\CreateRecurringTransactionAction;
use App\Modules\Ledger\Actions\UpdateRecurringTransactionAction;
use App\Modules\Ledger\Data\CreateRecurringTransactionData;
use App\Modules\Ledger\Data\UpdateRecurringTransactionData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RecurringTransactionController extends Controller
{
    public function index(Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RecurringTransaction::class);

        $query = RecurringTransaction::query()
            ->forLedger($ledger->id)
            ->with(['creditAccount', 'debitAccount'])
            ->orderByDesc('valid_from');

        if (request()->boolean('include_inactive', true)) {
            $query->withTrashed();
        } else {
            $query->whereNull('valid_to');
        }

        return RecurringTransactionResource::collection($query->get());
    }

    public function store(
        StoreRecurringTransactionRequest $request,
        Ledger $ledger,
        CreateRecurringTransactionAction $action
    ): JsonResponse {
        $data = CreateRecurringTransactionData::fromArray($request->validated());
        $blueprint = $action->execute($ledger, $data);

        return RecurringTransactionResource::make($blueprint->load(['creditAccount', 'debitAccount']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateRecurringTransactionRequest $request,
        Ledger $ledger,
        RecurringTransaction $recurringTransaction,
        UpdateRecurringTransactionAction $action
    ): RecurringTransactionResource {
        $this->authorize('update', $recurringTransaction);

        $data = UpdateRecurringTransactionData::fromArray($request->validated());

        $updated = $action->execute($recurringTransaction, $data);

        return RecurringTransactionResource::make(
            $updated->load(['creditAccount', 'debitAccount']),
        );
    }

    public function destroy(Ledger $ledger, RecurringTransaction $recurringTransaction): JsonResponse
    {
        $this->authorize('delete', $recurringTransaction);

        $recurringTransaction->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
