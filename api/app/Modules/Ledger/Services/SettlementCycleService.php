<?php

namespace App\Modules\Ledger\Services;

use App\Models\Ledger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SettlementCycleService
{
    /**
     * Calendar month of `$date` (preview/lookup). Cutoff-based due settlement uses {@see resolveDuePeriodEnd}.
     *
     * @return array{period_start:string, period_end:string}
     */
    public function resolvePeriodForDate(Ledger $ledger, string $date): array
    {
        $timezone = $ledger->settlement_timezone ?? 'UTC';
        $reference = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $periodEnd = $reference->endOfMonth();
        $periodStart = $periodEnd->startOfMonth();

        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
        ];
    }

    /**
     * @return array{period_start:string, period_end:string}
     */
    public function resolveNextPendingPeriod(Ledger $ledger): array
    {
        $oldestUnsettled = \App\Models\Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', '!=', \App\Enums\TransactionType::Settlement->value)
            ->whereNull('settlement_id')
            ->orderBy('date', 'asc')
            ->first();

        if ($oldestUnsettled) {
            $date = is_string($oldestUnsettled->date) ? $oldestUnsettled->date : $oldestUnsettled->date->toDateString();

            return $this->resolvePeriodForDate($ledger, $date);
        }

        return $this->resolvePeriodForDate($ledger, now()->toDateString());
    }

    /**
     * @return list<array{period_start:string, period_end:string, label:string, status:string, executed_at:?string}>
     */
    public function getAvailablePeriods(Ledger $ledger): array
    {
        $earliestTx = \App\Models\Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->orderBy('date', 'asc')
            ->first();

        $timezone = $ledger->settlement_timezone ?? 'UTC';
        $startDateStr = $earliestTx ? (is_string($earliestTx->date) ? $earliestTx->date : $earliestTx->date->toDateString()) : $ledger->created_at->toDateString();

        $current = CarbonImmutable::parse($startDateStr, $timezone)->startOfMonth();
        $end = CarbonImmutable::now($timezone)->endOfMonth();

        $settlements = \App\Models\Settlement::query()
            ->where('ledger_id', $ledger->id)
            ->get()
            ->keyBy(fn ($s) => is_string($s->period_end) ? mb_substr($s->period_end, 0, 10) : $s->period_end->toDateString());

        $periods = [];

        while ($current <= $end) {
            $pStart = $current->startOfMonth()->toDateString();
            $pEnd = $current->endOfMonth()->toDateString();
            $settlement = $settlements[$pEnd] ?? null;

            $isSettled = $settlement && $settlement->executed_at !== null;
            $status = $isSettled ? 'settled' : ($current->isFuture() ? 'future' : 'open');

            $periods[] = [
                'period_start' => $pStart,
                'period_end' => $pEnd,
                'label' => $current->format('F Y'),
                'status' => $status,
                'executed_at' => $settlement?->executed_at?->toIso8601String(),
            ];

            $current = $current->addMonth();
        }

        return $periods;
    }

    /**
     * @return array{period_start:string, period_end:string}
     */
    public function resolvePeriodForPeriodEnd(Ledger $ledger, string $periodEnd): array
    {
        $timezone = $ledger->settlement_timezone ?? 'UTC';
        $parsedPeriodEnd = CarbonImmutable::parse($periodEnd, $timezone)->endOfDay();
        $resolvedEnd = $parsedPeriodEnd->endOfMonth();
        $resolvedStart = $resolvedEnd->startOfMonth();

        return [
            'period_start' => $resolvedStart->toDateString(),
            'period_end' => $resolvedEnd->toDateString(),
        ];
    }

    public function resolveDuePeriodEnd(Ledger $ledger, CarbonInterface $nowUtc): ?string
    {
        if (
            $ledger->settlement_timezone === null
            || $ledger->settlement_cutoff_day === null
            || $ledger->settlement_cutoff_time === null
        ) {
            return null;
        }

        $timezone = $ledger->settlement_timezone;
        $now = CarbonImmutable::instance($nowUtc)->setTimezone($timezone);
        $currentCutoff = $this->cutoffForMonth($ledger, $now->year, $now->month);

        if ($now->lessThan($currentCutoff)) {
            return null;
        }

        return $now->subMonthNoOverflow()->endOfMonth()->toDateString();
    }

    private function cutoffForMonth(Ledger $ledger, int $year, int $month): CarbonImmutable
    {
        $timezone = $ledger->settlement_timezone ?? 'UTC';
        $cutoffDay = (int) ($ledger->settlement_cutoff_day ?? 1);
        $cutoffTime = (string) ($ledger->settlement_cutoff_time ?? '00:00:00');

        $monthStart = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone);
        $day = min(max($cutoffDay, 1), $monthStart->daysInMonth);
        [$hour, $minute, $second] = array_map('intval', explode(':', $cutoffTime));

        return $monthStart
            ->setDay($day)
            ->setTime($hour, $minute, $second);
    }
}
