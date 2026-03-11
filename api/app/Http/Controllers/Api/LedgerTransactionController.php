<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use App\Modules\Ledger\Data\TransactionIndexFiltersData;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use App\Modules\Ledger\Queries\LedgerTransactionIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LedgerTransactionController extends Controller
{
    public function index(
        IndexTransactionRequest $request,
        Ledger $ledger,
        LedgerTransactionIndexQuery $query
    ): AnonymousResourceCollection {
        return TransactionResource::collection(
            $query->execute($ledger, TransactionIndexFiltersData::fromArray([
                'from_date' => $request->validated('from_date'),
                'to_date' => $request->validated('to_date'),
                'account_id' => $request->validated('account_id'),
            ])),
        );
    }

    public function store(
        StoreTransactionRequest $request,
        Ledger $ledger,
        PostManualTransactionAction $action
    ): JsonResponse {
        try {
            $transaction = $action->execute(
                PostManualTransactionData::fromArray([
                    ...$request->validated(),
                    'ledger_id' => $ledger->id,
                    'description' => $request->validated('description'),
                    'type' => TransactionType::Manual->value,
                ]),
            );
        } catch (InvalidLedgerPostingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return TransactionResource::make($transaction)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Ledger $ledger, Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $transaction);

        return TransactionResource::make(
            $transaction->load(['payerAccount', 'postings.account']),
        );
    }
}
