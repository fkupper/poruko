<?php

namespace App\Http\Resources;

use App\Models\BankAccountMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccountMapping
 */
class BankAccountMappingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ledger_id' => $this->ledger_id,
            'external_account_name' => $this->external_account_name,
            'masked_identifier' => $this->masked_identifier,
            'ownership_type' => $this->ownership_type,
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn () => $this->account?->name),
            'suggested_account_id' => $this->suggested_account_id,
            'suggested_account_name' => $this->whenLoaded(
                'suggestedAccount',
                fn () => $this->suggestedAccount?->name,
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
