<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\PostingDirection;
use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\Posting;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Modules\Ledger\Services\SettlementCycleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordMidCycleSettlementTransferAction
{
    public function __construct(
        private PreviewSettlementAction $previewSettlementAction,
        private SettlementCycleService $settlementCycleService,
    ) {}

    public function execute(
        Ledger $ledger,
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
                return $this->resolveIdempotentRetry(
                    $existing,
                    $periodEnd,
                    $fromAccountId,
                    $toAccountId,
                    $amount,
                );
            }

            $period = $this->settlementCycleService->resolvePeriodForPeriodEnd($ledger, $periodEnd);
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

            if (! is_array($suggestedTransfer)) {
                throw ValidationException::withMessages([
                    'from_account_id' => ['This transfer is not currently required for the selected cycle.'],
                ]);
            }

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

            $recordedAt = CarbonImmutable::now($ledger->settlement_timezone ?? 'UTC');
            $periodStart = CarbonImmutable::parse($period['period_start'], $ledger->settlement_timezone ?? 'UTC');
            $resolvedPeriodEnd = CarbonImmutable::parse($period['period_end'], $ledger->settlement_timezone ?? 'UTC');

            if ($recordedAt->isBefore($periodStart)) {
                throw ValidationException::withMessages([
                    'period_end' => ['Transfers cannot be recorded against a future cycle.'],
                ]);
            }

            $accountingDate = $recordedAt->isAfter($resolvedPeriodEnd)
                ? $resolvedPeriodEnd
                : $recordedAt;

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
}
