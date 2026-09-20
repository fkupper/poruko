<?php

namespace App\Modules\Ledger\Data;

use App\Enums\TransactionSource;

final readonly class CreatePendingTransactionData
{
    /**
     * @param array<string, mixed> $rawData
     * @param list<array{user_id:int, share?:int|float}> $participants
     */
    public function __construct(
        public int $ledgerId,
        public int $userId,
        public ?int $payerAccountId,
        public ?int $destinationAccountId,
        public array $rawData,
        public ?string $description,
        public ?int $amount,
        public ?string $splitRule,
        public array $participants,
        public ?string $date,
        public string $source,
        public ?float $confidence = null,
        public ?string $rationale = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var list<array{user_id:int, share?:int|float}> $participants */
        $participants = is_array($payload['participants'] ?? null) ? $payload['participants'] : [];

        return new self(
            ledgerId: (int) $payload['ledger_id'],
            userId: (int) $payload['user_id'],
            payerAccountId: isset($payload['payer_account_id']) ? (int) $payload['payer_account_id'] : null,
            destinationAccountId: isset($payload['destination_account_id']) ? (int) $payload['destination_account_id'] : null,
            rawData: is_array($payload['raw_data'] ?? null) ? $payload['raw_data'] : [],
            description: isset($payload['description']) ? (string) $payload['description'] : null,
            amount: isset($payload['amount']) ? (int) $payload['amount'] : null,
            splitRule: isset($payload['split_rule']) ? (string) $payload['split_rule'] : null,
            participants: $participants,
            date: isset($payload['date']) ? (string) $payload['date'] : null,
            source: (string) ($payload['source'] ?? TransactionSource::Manual->value),
            confidence: isset($payload['confidence']) ? (float) $payload['confidence'] : null,
            rationale: isset($payload['rationale']) ? (string) $payload['rationale'] : null,
        );
    }
}
