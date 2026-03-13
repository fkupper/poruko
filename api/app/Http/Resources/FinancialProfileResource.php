<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ledger_id' => $this->ledger_id,
            'user_id' => $this->user_id,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_to' => $this->valid_to?->format('Y-m-d'),
            'incomes' => $this->incomes,
            'deductions' => $this->deductions,
            'computed' => [
                'total_income' => $this->resource->totalIncome(),
                'total_deductions' => $this->resource->totalDeductions(),
                'shareable_income' => $this->resource->shareableIncome(),
            ],
        ];
    }
}
