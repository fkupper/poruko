<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchReviewPendingTransactionsRequest;
use App\Http\Requests\RejectPendingTransactionRequest;
use App\Http\Resources\PendingTransactionResource;
use App\Http\Resources\TransactionResource;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\ApprovePendingTransactionsAction;
use App\Modules\Ledger\Actions\RejectPendingTransactionsAction;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use App\Modules\Ledger\Exceptions\PendingTransactionReviewException;
use App\Modules\Ledger\Queries\PendingTransactionIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PendingTransactionController extends Controller
{
    public function index(
        Ledger $ledger,
        PendingTransactionIndexQuery $query,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', [PendingTransaction::class, $ledger]);

        return PendingTransactionResource::collection($query->execute($ledger));
    }

    public function approve(
        Request $request,
        Ledger $ledger,
        PendingTransaction $pendingTransaction,
        ApprovePendingTransactionsAction $action,
    ): JsonResponse {
        $this->authorize('approve', $pendingTransaction);

        /** @var User $reviewer */
        $reviewer = $request->user();

        try {
            $transaction = $action
                ->execute($ledger, $reviewer, [$pendingTransaction->id])
                ->firstOrFail()
                ->load(['payerAccount', 'destinationAccount', 'postings']);
        } catch (InvalidLedgerPostingException|PendingTransactionReviewException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return TransactionResource::make($transaction)->response();
    }

    public function approveBatch(
        BatchReviewPendingTransactionsRequest $request,
        Ledger $ledger,
        ApprovePendingTransactionsAction $action,
    ): AnonymousResourceCollection|JsonResponse {
        /** @var list<int> $pendingTransactionIds */
        $pendingTransactionIds = $request->validated('pending_transaction_ids');
        /** @var User $reviewer */
        $reviewer = $request->user();

        try {
            $transactions = $action->execute($ledger, $reviewer, $pendingTransactionIds);
        } catch (InvalidLedgerPostingException|PendingTransactionReviewException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $transactions->each(
            fn (Transaction $transaction) => $transaction->load(['payerAccount', 'destinationAccount', 'postings']),
        );

        return TransactionResource::collection($transactions);
    }

    public function reject(
        RejectPendingTransactionRequest $request,
        Ledger $ledger,
        PendingTransaction $pendingTransaction,
        RejectPendingTransactionsAction $action,
    ): JsonResponse {
        /** @var User $reviewer */
        $reviewer = $request->user();
        $reason = $request->validated('reason');

        try {
            $rejected = $action
                ->execute(
                    $ledger,
                    $reviewer,
                    [$pendingTransaction->id],
                    is_string($reason) ? $reason : null,
                )
                ->firstOrFail()
                ->load(['proposer', 'payerAccount', 'destinationAccount']);
        } catch (PendingTransactionReviewException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return PendingTransactionResource::make($rejected)->response();
    }

    public function rejectBatch(
        BatchReviewPendingTransactionsRequest $request,
        Ledger $ledger,
        RejectPendingTransactionsAction $action,
    ): AnonymousResourceCollection|JsonResponse {
        /** @var list<int> $pendingTransactionIds */
        $pendingTransactionIds = $request->validated('pending_transaction_ids');
        /** @var User $reviewer */
        $reviewer = $request->user();
        $reason = $request->validated('reason');

        try {
            $rejected = $action->execute(
                $ledger,
                $reviewer,
                $pendingTransactionIds,
                is_string($reason) ? $reason : null,
            );
        } catch (PendingTransactionReviewException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rejected->each(
            fn (PendingTransaction $pendingTransaction) => $pendingTransaction->load(['proposer', 'payerAccount', 'destinationAccount']),
        );

        return PendingTransactionResource::collection($rejected);
    }
}
