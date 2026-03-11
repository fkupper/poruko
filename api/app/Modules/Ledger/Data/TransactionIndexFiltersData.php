<?php

namespace App\Modules\Ledger\Data;

final readonly class TransactionIndexFiltersData
{
    public function __construct(
        public ?string $fromDate,
        public ?string $toDate,
        public ?int $accountId,
    ) {}

    /**
     * @param  array{from_date:string|null, to_date:string|null, account_id:int|null}  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            fromDate: $validated['from_date'],
            toDate: $validated['to_date'],
            accountId: $validated['account_id'],
        );
    }
}
