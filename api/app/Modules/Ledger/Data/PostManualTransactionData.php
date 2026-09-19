<?php

namespace App\Modules\Ledger\Data;

use App\Enums\TransactionSource;

final readonly class PostManualTransactionData
{
    /**
     * @param list<array{user_id:int, share?:int}> $participants
     * @param array<string, mixed>|null $sourceMetadata
     */
    public function __construct(
        public int $ledgerId,
        public int $payerAccountId,
        public int $destinationAccountId,
        public int $amount,
        public string $splitRule,
        public array $participants,
        public string $date,
        public ?string $description = null,
        public string $type = 'manual',
        public string $source = TransactionSource::Manual->value,
        public ?array $sourceMetadata = null,
    ) {}

    /**
     * @param array<string, mixed> $payload Must contain ledger_id, payer_account_id, amount, split_rule, participants, date
     */
    public static function fromArray(array $payload): self
    {
        /** @var list<array{user_id: int, share?: int}> $participants */
        $participants = is_array($payload['participants'] ?? null) ? $payload['participants'] : [];

        return new self(
            ledgerId: (int) $payload['ledger_id'],
            payerAccountId: (int) $payload['payer_account_id'],
            destinationAccountId: (int) $payload['destination_account_id'],
            amount: (int) $payload['amount'],
            splitRule: (string) $payload['split_rule'],
            participants: $participants,
            date: (string) $payload['date'],
            description: $payload['description'] !== null ? (string) $payload['description'] : null,
            type: (string) ($payload['type'] ?? 'manual'),
            source: (string) ($payload['source'] ?? TransactionSource::Manual->value),
            sourceMetadata: is_array($payload['source_metadata'] ?? null) ? $payload['source_metadata'] : null,
        );
    }
}
