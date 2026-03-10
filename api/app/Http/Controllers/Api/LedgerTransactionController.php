<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LedgerTransactionController extends Controller
{
    public function index(IndexTransactionRequest $request, Ledger $ledger): AnonymousResourceCollection
    {
        $this->authorize('view', $ledger);

        $validated = $request->validated();

        $transactions = Transaction::query()
            ->with(['payerAccount', 'postings'])
            ->forLedger($ledger->id)
            ->betweenDates($validated['from_date'] ?? null, $validated['to_date'] ?? null)
            ->when(
                isset($validated['account_id']),
                fn ($query) => $query->where(function ($subQuery) use ($validated) {
                    $subQuery->where('payer_account_id', $validated['account_id'])
                        ->orWhereJsonContains('participants', [['account_id' => (int) $validated['account_id']]]);
                }),
            )
            ->latest('date')
            ->get();

        return TransactionResource::collection($transactions);
    }

    public function store(
        StoreTransactionRequest $request,
        Ledger $ledger,
        PostManualTransactionAction $action
    ): JsonResponse {
        $this->authorize('view', $ledger);

        try {
            $transaction = $action->execute([
                ...$request->validated(),
                'ledger_id' => $ledger->id,
            ]);
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
        $this->authorize('view', $ledger);
        abort_unless($transaction->ledger_id === $ledger->id, Response::HTTP_NOT_FOUND);

        return TransactionResource::make(
            $transaction->load(['payerAccount', 'postings.account']),
        );
    }
}
