<?php

namespace App\Http\Resources;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $credits = (int) ($this->resource->getAttribute('total_credits') ?? 0);
        $debits = (int) ($this->resource->getAttribute('total_debits') ?? 0);

        $calculatedBalance = $this->base_budget;

        if ($credits > 0 || $debits > 0) {
            $calculatedBalance += match ($this->type) {
                \App\Enums\AccountType::SplitClearing,
                \App\Enums\AccountType::UserFunding => $credits - $debits,
                default => $debits - $credits,
            };
        }

        return [
            'id' => $this->id,
            'ledger_id' => $this->ledger_id,
            'owner_id' => $this->owner_id,
            'type' => $this->type,
            'name' => $this->name,
            'code' => $this->code,
            'base_budget' => $this->base_budget,
            'balance' => $calculatedBalance,
            'is_main' => (bool) ($this->resource->getAttribute('is_main') ?? false),
            'owner_is_active' => (bool) ($this->resource->getAttribute('owner_is_active') ?? true),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
