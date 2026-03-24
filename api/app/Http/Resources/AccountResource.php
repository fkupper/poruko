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
        return [
            'id' => $this->id,
            'ledger_id' => $this->ledger_id,
            'owner_id' => $this->owner_id,
            'type' => $this->type,
            'name' => $this->name,
            'code' => $this->code,
            'is_main' => (bool) ($this->resource->getAttribute('is_main') ?? false),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
