<?php

namespace App\Modules\Ledger\Data;

final readonly class CreateRecurringTransactionData
{
    /**
     * @param list<array{user_id:int, share?:int|float}> $participants
     */
    public function __construct(
        public int $creditAccountId,
        public int $debitAccountId,
        public int $amount,
        public string $startDate,
        public string $splitRule,
        public array $participants = [],
        public ?string $description = null,
        public string $frequency = 'monthly',
    ) {}

    /**
     * @param array<string, mixed> $validated Validated request (credit_account_id, debit_account_id, amount, start_date, split_rule, etc.)
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            creditAccountId: (int) $validated['credit_account_id'],
            debitAccountId: (int) $validated['debit_account_id'],
            amount: (int) $validated['amount'],
            startDate: (string) $validated['start_date'],
            splitRule: (string) $validated['split_rule'],
            participants: $validated['participants'] ?? [],
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            frequency: (string) ($validated['frequency'] ?? 'monthly'),
        );
    }
}
