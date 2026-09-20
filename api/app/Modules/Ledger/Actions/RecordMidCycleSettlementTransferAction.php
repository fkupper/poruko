<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\PostingDirection;
use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Posting;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Services\MidCycleTransferAuthorization;
use App\Modules\Ledger\Services\SettlementCycleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordMidCycleSettlementTransferAction
{
    public function __construct(
        private PreviewSettlementAction $previewSettlementAction,
        private SettlementCycleService $settlementCycleService,
        private MidCycleTransferAuthorization $midCycleTransferAuthorization,
    ) {}

    public function execute(
        Ledger $ledger,
        User $actor,
        string $periodEnd,
        int $fromAccountId,
        int $toAccountId,
        int $amount,
        string $idempotencyKey,
    ): Transaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['The transfer amount must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use (
            $ledger,
            $actor,
            $periodEnd,
            $fromAccountId,
            $toAccountId,
            $amount,
            $idempotencyKey,
        ): Transaction {
            Ledger::query()
                ->whereKey($ledger->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Transaction::query()
                ->where('ledger_id', $ledger->id)
                ->where('settlement_transfer_key', $idempotencyKey)
                ->first();

            if ($existing instanceof Transaction) {
                $this->assertActorMayUseSource($actor, $fromAccountId);

                return $this->resolveIdempotentRetry(
                    $existing,
                    $periodEnd,
                    $fromAccountId,
                    $toAccountId,
                    $amount,
                );
            }

            $period = $this->settlementCycleService->resolvePeriodForPeriodEnd($ledger, $periodEnd);
            $timezone = $ledger->settlement_timezone ?? 'UTC';
            $recordedAt = CarbonImmutable::now($timezone);
            $periodStart = CarbonImmutable::parse($period['period_start'], $timezone);
            $resolvedPeriodEnd = CarbonImmutable::parse($period['period_end'], $timezone);

            if ($recordedAt->isBefore($periodStart)) {
                throw ValidationException::withMessages([
                    'period_end' => ['Transfers cannot be recorded against a future cycle.'],
                ]);
            }

            $accountingDate = $recordedAt->isAfter($resolvedPeriodEnd)
                ? $resolvedPeriodEnd
                : $recordedAt;

            $settlement = Settlement::query()
                ->where('ledger_id', $ledger->id)
                ->where('period_start', $period['period_start'])
                ->where('period_end', $period['period_end'])
                ->lockForUpdate()
                ->first();

            if ($settlement?->executed_at !== null) {
                throw ValidationException::withMessages([
                    'period_end' => ['Transfers cannot be recorded against a settled cycle.'],
                ]);
            }

            $preview = $this->previewSettlementAction->executeForPeriod($ledger, $period);
            $suggestedTransfer = collect($preview['required_transfers'])->first(
                static fn (array $transfer): bool => (int) $transfer['from_account_id'] === $fromAccountId
                    && (int) $transfer['to_account_id'] === $toAccountId,
            );

            if (!is_array($suggestedTransfer)) {
                throw ValidationException::withMessages([
                    'from_account_id' => ['This transfer is not currently required for the selected cycle.'],
                ]);
            }

            $this->assertActorMayUseSource($actor, $fromAccountId);

            $suggestedAmount = (int) $suggestedTransfer['amount'];

            if ($amount > $suggestedAmount) {
                throw ValidationException::withMessages([
                    'amount' => ["The transfer exceeds the remaining suggested amount of {$suggestedAmount}."],
                ]);
            }

            $settlement ??= Settlement::query()->create([
                'ledger_id' => $ledger->id,
                'period_start' => $period['period_start'],
                'period_end' => $period['period_end'],
                'executed_at' => null,
            ]);

            $transaction = Transaction::query()->create([
                'ledger_id' => $ledger->id,
                'settlement_id' => $settlement->id,
                'settlement_transfer_key' => $idempotencyKey,
                'payer_account_id' => $fromAccountId,
                'destination_account_id' => $toAccountId,
                'amount' => $amount,
                'type' => TransactionType::Settlement->value,
                'source' => TransactionSource::System->value,
                'source_metadata' => [
                    'operation' => 'mid_cycle_settlement_transfer',
                    'period_start' => $period['period_start'],
                    'period_end' => $period['period_end'],
                    'recorded_at' => $recordedAt->toIso8601String(),
                ],
                'split_rule' => TransactionSplitRule::Individual->value,
                'participants' => [],
                'description' => (string) $suggestedTransfer['instruction'],
                'date' => $accountingDate->toDateString(),
            ]);

            $now = CarbonImmutable::now();
            Posting::query()->insert([
                [
                    'transaction_id' => $transaction->id,
                    'account_id' => $fromAccountId,
                    'direction' => PostingDirection::Credit->value,
                    'amount' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'transaction_id' => $transaction->id,
                    'account_id' => $toAccountId,
                    'direction' => PostingDirection::Debit->value,
                    'amount' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            return $transaction->load(['payerAccount', 'destinationAccount', 'postings']);
        });
    }

    private function resolveIdempotentRetry(
        Transaction $transaction,
        string $periodEnd,
        int $fromAccountId,
        int $toAccountId,
        int $amount,
    ): Transaction {
        $metadataPeriodEnd = $transaction->source_metadata['period_end'] ?? null;

        if (
            $transaction->payer_account_id !== $fromAccountId
            || $transaction->destination_account_id !== $toAccountId
            || $transaction->amount !== $amount
            || $metadataPeriodEnd !== $periodEnd
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['This idempotency key was already used for a different transfer.'],
            ]);
        }

        return $transaction->load(['payerAccount', 'destinationAccount', 'postings']);
    }

    private function assertActorMayUseSource(User $actor, int $fromAccountId): void
    {
        $fromAccount = Account::query()->findOrFail($fromAccountId);
        $this->midCycleTransferAuthorization->assertCanUseSource($actor, $fromAccount);
    }
}
