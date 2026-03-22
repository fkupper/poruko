<?php

namespace App\Modules\Ledger\Data;

final readonly class MaterializeRecurringTransactionData
{
    public function __construct(
        public int $blueprintId,
        public string $periodStart,
        public string $periodEnd,
    ) {}

    /**
     * @param  array{period_start:string, period_end:string}  $period
     */
    public static function fromBlueprintAndPeriod(int $blueprintId, array $period): self
    {
        return new self(
            blueprintId: $blueprintId,
            periodStart: (string) $period['period_start'],
            periodEnd: (string) $period['period_end'],
        );
    }
}
