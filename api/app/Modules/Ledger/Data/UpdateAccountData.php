<?php

namespace App\Modules\Ledger\Data;

use App\Enums\AccountType;

final readonly class UpdateAccountData
{
    public function __construct(
        public ?string $name = null,
        public ?AccountType $type = null,
        public ?int $ownerId = null,
        public ?int $baseBudget = null,
        public ?string $code = null,
        public ?int $currentFunds = null,
    ) {}

    /**
     * @param array<string, mixed> $validated
     */
    public static function fromArray(array $validated): self
    {
        $type = null;

        if (isset($validated['type'])) {
            $type = $validated['type'] instanceof AccountType
                ? $validated['type']
                : AccountType::from((string) $validated['type']);
        }

        $baseBudgetRaw = $validated['base_budget'] ?? $validated['balance'] ?? null;

        return new self(
            name: isset($validated['name']) ? (string) $validated['name'] : null,
            type: $type,
            ownerId: isset($validated['owner_id']) && $validated['owner_id'] !== null ? (int) $validated['owner_id'] : null,
            baseBudget: $baseBudgetRaw !== null ? (int) $baseBudgetRaw : null,
            code: isset($validated['code']) && $validated['code'] !== null ? (string) $validated['code'] : null,
            currentFunds: isset($validated['current_funds']) ? (int) $validated['current_funds'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->type !== null) {
            $data['type'] = $this->type->value;
        }

        if ($this->ownerId !== null) {
            $data['owner_id'] = $this->ownerId;
        }

        if ($this->baseBudget !== null) {
            $data['base_budget'] = $this->baseBudget;
        }

        if ($this->code !== null) {
            $data['code'] = $this->code;
        }

        return $data;
    }
}
