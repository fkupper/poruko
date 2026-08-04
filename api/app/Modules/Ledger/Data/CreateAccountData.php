<?php

namespace App\Modules\Ledger\Data;

use App\Enums\AccountType;

final readonly class CreateAccountData
{
    public function __construct(
        public string $name,
        public AccountType $type,
        public ?int $ownerId = null,
        public ?int $baseBudget = null,
        public ?string $code = null,
    ) {}

    /**
     * @param array<string, mixed> $validated
     */
    public static function fromArray(array $validated): self
    {
        $type = $validated['type'] instanceof AccountType
            ? $validated['type']
            : AccountType::from((string) $validated['type']);

        $baseBudgetRaw = $validated['base_budget'] ?? $validated['balance'] ?? null;

        return new self(
            name: (string) $validated['name'],
            type: $type,
            ownerId: isset($validated['owner_id']) && $validated['owner_id'] !== null ? (int) $validated['owner_id'] : null,
            baseBudget: $baseBudgetRaw !== null ? (int) $baseBudgetRaw : null,
            code: isset($validated['code']) && $validated['code'] !== null ? (string) $validated['code'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type->value,
            'owner_id' => $this->ownerId,
            'base_budget' => $this->baseBudget ?? 0,
            'code' => $this->code,
        ], fn ($val) => $val !== null);
    }
}
