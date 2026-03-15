<?php

namespace App\Http\Resources;

use App\Models\Settlement;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Settlement
 */
class SettlementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $periodStart = $this->resource->period_start;
        $periodEnd = $this->resource->period_end;
        $executedAt = $this->resource->executed_at;

        return [
            'id' => $this->id,
            'ledger_id' => $this->ledger_id,
            'period_start' => $periodStart instanceof DateTimeInterface ? $periodStart->format('Y-m-d') : null,
            'period_end' => $periodEnd instanceof DateTimeInterface ? $periodEnd->format('Y-m-d') : null,
            'executed_at' => $executedAt instanceof DateTimeInterface ? $executedAt->format('c') : null,
            'confirmation_required' => $this->executed_at === null,
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
