<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Modules\Ledger\Actions\DeleteTransactionAction;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Actions\UpdateTransactionAction;
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
            $query->execute($ledger, TransactionIndexFiltersData::fromArray($request->validated())),
        );
    }

    public function store(
        StoreTransactionRequest $request,
        Ledger $ledger,
        PostManualTransactionAction $action
    ): JsonResponse {
        try {
            /** @var array{ledger_id:int, payer_account_id:int, destination_account_id:int, amount:int, split_rule:string, participants:list<array{user_id:int, share?:int}>, description:string|null, date:string, type:string} $payload */
            $payload = [
                ...$request->validated(),
                'ledger_id' => $ledger->id,
                'description' => $request->validated('description'),
                'type' => TransactionType::Manual->value,
                'participants' => $request->validated('participants') ?? [],
            ];
            $transaction = $action->execute(PostManualTransactionData::fromArray($payload));
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
            $transaction->load(['payerAccount', 'postings']),
        );
    }

    public function update(
        UpdateTransactionRequest $request,
        Ledger $ledger,
        Transaction $transaction,
        UpdateTransactionAction $action
    ): JsonResponse {
        try {
            /** @var array{ledger_id:int, payer_account_id:int, destination_account_id:int, amount:int, split_rule:string, participants:list<array{user_id:int, share?:int}>, description:string|null, date:string, type:string} $payload */
            $payload = [
                ...$request->validated(),
                'ledger_id' => $ledger->id,
                'description' => $request->validated('description'),
                'type' => $transaction->type->value,
                'participants' => $request->validated('participants') ?? [],
            ];
            $updatedTransaction = $action->execute($transaction, PostManualTransactionData::fromArray($payload));
        } catch (InvalidLedgerPostingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return TransactionResource::make($updatedTransaction)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(
        Ledger $ledger,
        Transaction $transaction,
        DeleteTransactionAction $action
    ): JsonResponse {
        $this->authorize('delete', $transaction);

        try {
            $action->execute($transaction);
        } catch (InvalidLedgerPostingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
