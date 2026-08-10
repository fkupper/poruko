<?php

namespace App\Modules\Ledger\Data;

final readonly class CreateLedgerData
{
    public function __construct(
        public string $name,
        public ?string $currency = null,
        public ?string $settlementMode = null,
        public ?int $settlementCutoffDay = null,
        public ?string $settlementTimezone = null,
        public ?bool $settlementAutoExecuteEnabled = null,
    ) {}

    /**
     * @param array<string, mixed> $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            name: (string) $validated['name'],
            currency: isset($validated['currency']) ? (string) $validated['currency'] : null,
            settlementMode: isset($validated['settlement_mode']) ? (string) $validated['settlement_mode'] : null,
            settlementCutoffDay: isset($validated['settlement_cutoff_day']) ? (int) $validated['settlement_cutoff_day'] : null,
            settlementTimezone: isset($validated['settlement_timezone']) ? (string) $validated['settlement_timezone'] : null,
            settlementAutoExecuteEnabled: isset($validated['settlement_auto_execute_enabled']) ? (bool) $validated['settlement_auto_execute_enabled'] : null,
        );
    }
}
