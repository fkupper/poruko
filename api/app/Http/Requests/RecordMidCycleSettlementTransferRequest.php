<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;

class RecordMidCycleSettlementTransferRequest extends FormRequest
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
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer', 'different:from_account_id'],
            'amount' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_end.required' => 'The settlement period end date is required.',
            'period_end.date' => 'The settlement period end must be a valid date.',
            'from_account_id.required' => 'The source account is required.',
            'to_account_id.required' => 'The destination account is required.',
            'to_account_id.different' => 'The destination account must differ from the source account.',
            'amount.min' => 'The transfer amount must be greater than zero.',
            'idempotency_key.required' => 'An idempotency key is required.',
            'idempotency_key.uuid' => 'The idempotency key must be a valid UUID.',
        ];
    }
}
