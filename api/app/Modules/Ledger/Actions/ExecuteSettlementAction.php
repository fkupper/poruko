<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\PostingDirection;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\Posting;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Modules\Ledger\Services\SettlementCycleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

readonly class ExecuteSettlementAction
{
    public function __construct(
        private readonly PreviewSettlementAction $previewSettlementAction,
        private readonly SettlementCycleService $settlementCycleService,
    ) {}

    public function execute(Ledger $ledger, string $periodEnd): Settlement
    {
        $period = $this->settlementCycleService->resolvePeriodForPeriodEnd($ledger, $periodEnd);
        $preview = $this->previewSettlementAction->executeForPeriodEnd($ledger, $periodEnd);
        $transferInstructions = $preview['required_transfers'];

        return DB::transaction(function () use ($ledger, $period, $transferInstructions): Settlement {
            /** @var Settlement $settlement */
            $settlement = Settlement::query()->firstOrCreate(
                [
                    'ledger_id' => $ledger->id,
                    'period_start' => $period['period_start'],
                    'period_end' => $period['period_end'],
                ],
                [
                    'executed_at' => null,
                ],
            );

            if ($settlement->executed_at !== null) {
                return $settlement;
            }

            foreach ($transferInstructions as $instruction) {
                $fromAccountId = (int) $instruction['from_account_id'];
                $toAccountId = (int) $instruction['to_account_id'];
                $amount = (int) $instruction['amount'];

                if ($fromAccountId <= 0 || $toAccountId <= 0 || $amount <= 0) {
                    continue;
                }

                $transaction = Transaction::query()->create([
                    'ledger_id' => $ledger->id,
                    'settlement_id' => $settlement->id,
                    'credit_account_id' => $fromAccountId,
                    'debit_account_id' => $toAccountId,
                    'amount' => $amount,
                    'type' => TransactionType::Settlement->value,
                    'split_rule' => TransactionSplitRule::Individual->value,
                    'participants' => [],
                    'description' => "Settlement transfer for {$period['period_end']}",
                    'date' => $period['period_end'],
                ]);

                Posting::query()->insert([
                    [
                        'transaction_id' => $transaction->id,
                        'account_id' => $fromAccountId,
                        'direction' => PostingDirection::Credit->value,
                        'amount' => $amount,
                        'created_at' => CarbonImmutable::now(),
                        'updated_at' => CarbonImmutable::now(),
                    ],
                    [
                        'transaction_id' => $transaction->id,
                        'account_id' => $toAccountId,
                        'direction' => PostingDirection::Debit->value,
                        'amount' => $amount,
                        'created_at' => CarbonImmutable::now(),
                        'updated_at' => CarbonImmutable::now(),
                    ],
                ]);
            }

            $settlement->update([
                'executed_at' => now(),
            ]);

            return $settlement->refresh();
        });
    }
}
