<?php

namespace App\Http\Resources;

use App\Models\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ledger
 */
class LedgerResource extends JsonResource
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
            'name' => $this->name,
            'settlement_mode' => $this->settlement_mode,
            'settlement_timezone' => $this->settlement_timezone,
            'settlement_cutoff_day' => $this->settlement_cutoff_day,
            'settlement_cutoff_time' => $this->settlement_cutoff_time,
            'settlement_auto_execute_enabled' => $this->settlement_auto_execute_enabled,
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
