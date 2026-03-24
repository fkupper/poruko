<?php

namespace App\Modules\Ledger\Data;

final readonly class UpdateRecurringTransactionData
{
    /**
     * @param list<array{user_id:int, share?:int|float}>|null $participants
     */
    public function __construct(
        public ?int $creditAccountId = null,
        public ?int $debitAccountId = null,
        public ?int $amount = null,
        public ?string $description = null,
        public ?string $splitRule = null,
        public ?array $participants = null,
        public ?string $frequency = null,
        public ?string $startDate = null,
    ) {}

    /**
     * @param array<string, mixed> $validated Validated request data (only non-null values are applied)
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            creditAccountId: isset($validated['credit_account_id']) ? (int) $validated['credit_account_id'] : null,
            debitAccountId: isset($validated['debit_account_id']) ? (int) $validated['debit_account_id'] : null,
            amount: isset($validated['amount']) ? (int) $validated['amount'] : null,
            description: array_key_exists('description', $validated) ? ($validated['description'] !== null ? (string) $validated['description'] : null) : null,
            splitRule: isset($validated['split_rule']) ? (string) $validated['split_rule'] : null,
            participants: array_key_exists('participants', $validated) ? $validated['participants'] : null,
            frequency: isset($validated['frequency']) ? (string) $validated['frequency'] : null,
            startDate: isset($validated['start_date']) ? (string) $validated['start_date'] : null,
        );
    }

    /**
     * @return array<string, mixed> Attributes to merge (only non-null values)
     */
    public function toAttributes(): array
    {
        return array_filter([
            'credit_account_id' => $this->creditAccountId,
            'debit_account_id' => $this->debitAccountId,
            'amount' => $this->amount,
            'description' => $this->description,
            'split_rule' => $this->splitRule,
            'participants' => $this->participants,
            'frequency' => $this->frequency,
            'valid_from' => $this->startDate,
        ], fn ($v) => $v !== null);
    }
}
