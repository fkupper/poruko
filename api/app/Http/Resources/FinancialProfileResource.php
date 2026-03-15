<?php

namespace App\Http\Resources;

use App\Models\FinancialProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialProfile
 */
class FinancialProfileResource extends JsonResource
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
            'user_id' => $this->user_id,
            'valid_from' => $this->resource->valid_from?->format('Y-m-d'),
            'valid_to' => $this->resource->valid_to?->format('Y-m-d'),
            'incomes' => $this->incomes,
            'deductions' => $this->deductions,
            'computed' => [
                'total_income' => $this->resource->total_income,
                'total_deductions' => $this->resource->total_deductions,
                'shareable_income' => $this->resource->shareable_income,
            ],
        ];
    }
}
