<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmSettlementCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('manageSettlements', $ledger) === true;
    }

    protected function prepareForValidation(): void
    {
        $periodEnd = $this->input('period_end') ?? $this->route('cycle');

        if ($periodEnd !== null) {
            $this->merge(['period_end' => $periodEnd]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period_end' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_end.required' => 'The period end date is required.',
            'period_end.date' => 'The period end must be a valid date.',
        ];
    }
}
