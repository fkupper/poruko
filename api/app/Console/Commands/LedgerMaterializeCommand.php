<?php

namespace App\Console\Commands;

use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Modules\Ledger\Actions\PostRecurringTransactionAction;
use App\Modules\Ledger\Data\MaterializeRecurringTransactionData;
use App\Modules\Ledger\Services\RecurringPeriodService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class LedgerMaterializeCommand extends Command
{
    protected $signature = 'ledger:materialize';

    protected $description = 'Materialize active recurring transaction blueprints into ledger transactions';

    public function handle(
        PostRecurringTransactionAction $postRecurringAction,
        RecurringPeriodService $periodService
    ): int {
        $now = Carbon::now();

        $ledgers = Ledger::query()->get();

        $materialized = 0;

        foreach ($ledgers as $ledger) {
            $blueprints = RecurringTransaction::query()
                ->forLedger($ledger->id)
                ->active()
                ->get();

            foreach ($blueprints as $blueprint) {
                $period = $periodService->resolvePeriodForNow($ledger, $blueprint->frequency, $now);

                if (!$this->blueprintAppliesToPeriod($blueprint, $period)) {
                    continue;
                }

                if ($this->alreadyMaterialized($blueprint, $period)) {
                    continue;
                }

                try {
                    $data = MaterializeRecurringTransactionData::fromBlueprintAndPeriod($blueprint->id, $period);
                    $postRecurringAction->execute($data);
                    $materialized++;
                } catch (Throwable $e) {
                    $this->error("Failed to materialize blueprint {$blueprint->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Materialized {$materialized} recurring transaction(s).");

        return self::SUCCESS;
    }

    /**
     * @param array{period_start:string, period_end:string} $period
     */
    private function blueprintAppliesToPeriod(RecurringTransaction $blueprint, array $period): bool
    {
        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        if ($blueprint->valid_from->format('Y-m-d') > $periodStart) {
            return false;
        }

        if ($blueprint->valid_to !== null && $blueprint->valid_to->format('Y-m-d') < $periodEnd) {
            return false;
        }

        return true;
    }

    /**
     * @param array{period_start:string, period_end:string} $period
     */
    private function alreadyMaterialized(RecurringTransaction $blueprint, array $period): bool
    {
        return Transaction::query()
            ->where('source_recurring_transaction_id', $blueprint->id)
            ->whereDate('date', '>=', $period['period_start'])
            ->whereDate('date', '<=', $period['period_end'])
            ->exists();
    }
}
