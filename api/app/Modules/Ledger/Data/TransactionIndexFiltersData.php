<?php

namespace App\Modules\Ledger\Data;

final readonly class TransactionIndexFiltersData
{
    public function __construct(
        public ?string $fromDate,
        public ?string $toDate,
        public ?int $accountId,
        public int $perPage = 15,
        public int $page = 1,
    ) {}

    /**
     * @param array{from_date?:string|null, to_date?:string|null, account_id?:int|null, per_page?:int, page?:int} $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
            accountId: $validated['account_id'] ?? null,
            perPage: (int) ($validated['per_page'] ?? 15),
            page: (int) ($validated['page'] ?? 1),
        );
    }
}
