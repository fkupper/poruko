<?php

namespace App\Modules\Ledger\Services;

use App\Enums\RecurringFrequency;
use App\Models\Ledger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class RecurringPeriodService
{
    /**
     * @return array{period_start:string, period_end:string}
     */
    public function resolvePeriodForNow(Ledger $ledger, RecurringFrequency $frequency, CarbonInterface $nowUtc): array
    {
        $timezone = $ledger->settlement_timezone ?? 'UTC';
        $now = CarbonImmutable::instance($nowUtc)->setTimezone($timezone)->startOfDay();

        return match ($frequency) {
            RecurringFrequency::Weekly => [
                'period_start' => $now->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
                'period_end' => $now->endOfWeek(CarbonImmutable::SUNDAY)->toDateString(),
            ],
            RecurringFrequency::Monthly => [
                'period_start' => $now->startOfMonth()->toDateString(),
                'period_end' => $now->endOfMonth()->toDateString(),
            ],
            RecurringFrequency::Annually => [
                'period_start' => $now->startOfYear()->toDateString(),
                'period_end' => $now->endOfYear()->toDateString(),
            ],
        };
    }
}
