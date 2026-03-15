<?php

namespace App\Modules\Ledger\Services;

use App\Models\Ledger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SettlementCycleService
{
    /**
     * @return array{period_start:string, period_end:string}
     */
    public function resolvePeriodForDate(Ledger $ledger, string $date): array
    {
        $timezone = $ledger->settlement_timezone ?? 'UTC';
        $reference = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $periodEnd = $reference->subMonthNoOverflow()->endOfMonth();
        $periodStart = $periodEnd->startOfMonth();

        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
        ];
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
