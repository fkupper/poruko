<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatchReviewPendingTransactionsRequest;
use App\Http\Requests\RejectPendingTransactionRequest;
use App\Http\Requests\StorePendingTransactionRequest;
use App\Http\Requests\UpdatePendingTransactionRequest;
use App\Http\Resources\PendingTransactionResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\ApprovePendingTransactionsAction;
use App\Modules\Ledger\Actions\CreatePendingTransactionAction;
use App\Modules\Ledger\Actions\RejectPendingTransactionsAction;
use App\Modules\Ledger\Data\CreatePendingTransactionData;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use App\Modules\Ledger\Exceptions\PendingTransactionReviewException;
use App\Modules\Ledger\Queries\PendingTransactionIndexQuery;
use App\Modules\Mcp\Exceptions\McpAuthorizationException;
use App\Modules\Mcp\Services\ActorAccountAuthorizationService;
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

    public function store(
        StorePendingTransactionRequest $request,
        Ledger $ledger,
        CreatePendingTransactionAction $action,
        ActorAccountAuthorizationService $accountAuthorization,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $payer = Account::query()->findOrFail((int) $validated['payer_account_id']);

        try {
            $accountAuthorization->assertMayUsePayerAccount($user, $payer);
            $pending = $action->execute(CreatePendingTransactionData::fromArray([
                ...$validated,
                'ledger_id' => $ledger->id,
                'user_id' => $user->id,
                'description' => $validated['description'] ?? null,
                'source' => TransactionSource::Mcp->value,
                'raw_data' => [
                    'description' => $validated['description'] ?? null,
                    'origin' => 'mcp',
                ],
            ]));
        } catch (InvalidLedgerPostingException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (McpAuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }

        return PendingTransactionResource::make(
            $pending->load(['proposer', 'payerAccount', 'destinationAccount']),
        )
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdatePendingTransactionRequest $request,
        Ledger $ledger,
        PendingTransaction $pendingTransaction,
    ): PendingTransactionResource|JsonResponse {
        if ($pendingTransaction->status !== \App\Enums\PendingTransactionStatus::Pending) {
            return response()->json([
                'message' => 'Only pending transaction proposals can be edited.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validated();
        $updates = [];
        $fieldMap = [
            'payer_account_id' => 'payer_account_id',
            'destination_account_id' => 'destination_account_id',
            'description' => 'suggested_description',
            'amount' => 'suggested_amount',
            'date' => 'date',
            'split_rule' => 'suggested_split_rule',
            'participants' => 'suggested_participants',
        ];

        foreach ($fieldMap as $requestField => $modelField) {
            if (array_key_exists($requestField, $validated)) {
                $updates[$modelField] = $validated[$requestField];
            }
        }

        $pendingTransaction->update($updates);

        return PendingTransactionResource::make(
            $pendingTransaction->fresh(['proposer', 'payerAccount', 'destinationAccount'])
                ?? $pendingTransaction,
        );
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

        return TransactionResource::make($transaction)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
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
