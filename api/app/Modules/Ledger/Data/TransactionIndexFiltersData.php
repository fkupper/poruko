<?php

namespace App\Modules\Ledger\Data;

final readonly class TransactionIndexFiltersData
{
    /**
     * @param list<int>|null $creatorUserIds
     * @param list<string>|null $splitRules
     * @param list<string>|null $types
     * @param list<int>|null $accountIds
     */
    public function __construct(
        public ?string $fromDate = null,
        public ?string $toDate = null,
        public ?int $accountId = null,
        public ?array $creatorUserIds = null,
        public ?array $splitRules = null,
        public ?array $types = null,
        public ?int $settlementId = null,
        public ?array $accountIds = null,
        public int $perPage = 15,
        public int $page = 1,
    ) {}

    /**
     * @param array{from_date?:string|null, to_date?:string|null, account_id?:int|null, creator_user_ids?:list<int>|null, split_rules?:list<string>|null, types?:list<string>|null, settlement_id?:int|null, account_ids?:list<int>|null, per_page?:int, page?:int} $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
            accountId: isset($validated['account_id']) ? (int) $validated['account_id'] : null,
            creatorUserIds: isset($validated['creator_user_ids']) ? array_map('intval', (array) $validated['creator_user_ids']) : null,
            splitRules: isset($validated['split_rules']) ? (array) $validated['split_rules'] : null,
            types: isset($validated['types']) ? (array) $validated['types'] : null,
            settlementId: isset($validated['settlement_id']) ? (int) $validated['settlement_id'] : null,
            accountIds: isset($validated['account_ids']) ? array_map('intval', (array) $validated['account_ids']) : null,
            perPage: (int) ($validated['per_page'] ?? 15),
            page: (int) ($validated['page'] ?? 1),
        );
    }
}
