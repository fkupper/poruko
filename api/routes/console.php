<?php

use App\Jobs\ExecuteSettlementJob;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Modules\Ledger\Actions\PreviewSettlementAction;
use App\Modules\Ledger\Services\SettlementCycleService;
use App\Modules\Ledger\Services\SettlementSafetyGateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('settlements:run-due', function (): void {
    $now = Carbon::now();
    $cycleService = app(SettlementCycleService::class);
    $previewAction = app(PreviewSettlementAction::class);
    $safetyGate = app(SettlementSafetyGateService::class);

    $dueLedgers = Ledger::query()
        ->whereNotNull('settlement_timezone')
        ->whereNotNull('settlement_cutoff_day')
        ->whereNotNull('settlement_cutoff_time')
        ->get();

    foreach ($dueLedgers as $ledger) {
        $duePeriodEnd = $cycleService->resolveDuePeriodEnd($ledger, $now);

        if ($duePeriodEnd === null) {
            continue;
        }

        $period = $cycleService->resolvePeriodForPeriodEnd($ledger, $duePeriodEnd);
        $exists = Settlement::query()
            ->where('ledger_id', $ledger->id)
            ->where('period_start', $period['period_start'])
            ->where('period_end', $period['period_end'])
            ->exists();

        if ($exists) {
            continue;
        }

        $preview = $previewAction->executeForPeriodEnd($ledger, $duePeriodEnd);
        $gate = $safetyGate->evaluate($ledger, $preview);

        if ($gate['auto_allowed']) {
            ExecuteSettlementJob::dispatch(
                $ledger->id,
                $duePeriodEnd,
            );

            continue;
        }

        Settlement::query()->create([
            'ledger_id' => $ledger->id,
            'period_start' => $period['period_start'],
            'period_end' => $period['period_end'],
            'executed_at' => null,
        ]);
    }
})->purpose('Dispatch settlement jobs for due ledgers');

Schedule::command('settlements:run-due')->everyMinute();
Schedule::command('ledger:materialize')->hourly();
