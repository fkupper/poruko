<?php

namespace App\Modules\Ledger\Data;

final readonly class PostManualTransactionData
{
    /**
     * @param list<array{user_id:int, share?:int}> $participants
     */
    public function __construct(
        public int $ledgerId,
        public int $creditAccountId,
        public int $debitAccountId,
        public int $amount,
        public string $splitRule,
        public array $participants,
        public string $date,
        public ?string $description = null,
        public string $type = 'manual',
    ) {}

    /**
     * @param array<string, mixed> $payload Must contain ledger_id, credit_account_id, debit_account_id, amount, split_rule, participants, date
     */
    public static function fromArray(array $payload): self
    {
        /** @var list<array{user_id: int, share?: int}> $participants */
        $participants = is_array($payload['participants'] ?? null) ? $payload['participants'] : [];

        return new self(
            ledgerId: (int) $payload['ledger_id'],
            creditAccountId: (int) $payload['credit_account_id'],
            debitAccountId: (int) $payload['debit_account_id'],
            amount: (int) $payload['amount'],
            splitRule: (string) $payload['split_rule'],
            participants: $participants,
            date: (string) $payload['date'],
            description: $payload['description'] !== null ? (string) $payload['description'] : null,
            type: (string) ($payload['type'] ?? 'manual'),
        );
    }
}
