<?php

namespace App\Modules\Mcp\Actions;

use App\Enums\McpPostMode;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\CreatePendingTransactionAction;
use App\Modules\Ledger\Actions\PostManualTransactionAction;
use App\Modules\Ledger\Data\CreatePendingTransactionData;
use App\Modules\Ledger\Data\PostManualTransactionData;
use App\Modules\Mcp\Services\ActorAccountAuthorizationService;

final readonly class SubmitMcpTransactionAction
{
    public function __construct(
        private CreatePendingTransactionAction $createPendingTransactionAction,
        private PostManualTransactionAction $postManualTransactionAction,
        private ActorAccountAuthorizationService $accountAuthorization,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array{mode: string, pending_transaction?: PendingTransaction, transaction?: Transaction}
     */
    public function execute(User $actor, Ledger $ledger, LedgerUser $settings, array $payload, bool $forceQueue = false): array
    {
        $payer = Account::query()->findOrFail((int) $payload['payer_account_id']);
        $this->accountAuthorization->assertMayUsePayerAccount($actor, $payer);

        $queue = $forceQueue || $settings->mcp_post_mode === McpPostMode::ApprovalQueue;

        if ($queue) {
            $pending = $this->createPendingTransactionAction->execute(CreatePendingTransactionData::fromArray([
                ...$payload,
                'ledger_id' => $ledger->id,
                'user_id' => $actor->id,
                'source' => TransactionSource::Mcp->value,
                'raw_data' => [
                    'description' => $payload['description'] ?? null,
                    'origin' => 'mcp',
                    'tool' => $payload['tool'] ?? 'post-transaction',
                ],
            ]));

            return [
                'mode' => 'approval_queue',
                'pending_transaction' => $pending->load(['proposer', 'payerAccount', 'destinationAccount']),
            ];
        }

        $transaction = $this->postManualTransactionAction->execute(PostManualTransactionData::fromArray([
            ...$payload,
            'ledger_id' => $ledger->id,
            'type' => TransactionType::Manual->value,
            'source' => TransactionSource::Mcp->value,
            'source_metadata' => [
                'tool' => $payload['tool'] ?? 'post-transaction',
            ],
        ]));

        return [
            'mode' => 'direct',
            'transaction' => $transaction->load(['payerAccount', 'destinationAccount', 'postings']),
        ];
    }
}
