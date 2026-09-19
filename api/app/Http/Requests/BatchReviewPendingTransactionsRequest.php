<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchReviewPendingTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('view', $ledger) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ledger = $this->route('ledger');
        $ledgerId = $ledger instanceof Ledger ? $ledger->id : null;

        return [
            'pending_transaction_ids' => ['required', 'array', 'min:1', 'max:100'],
            'pending_transaction_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('pending_transactions', 'id')->where('ledger_id', $ledgerId),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pending_transaction_ids.required' => 'Select at least one pending transaction.',
            'pending_transaction_ids.min' => 'Select at least one pending transaction.',
            'pending_transaction_ids.max' => 'No more than 100 transactions may be reviewed at once.',
            'pending_transaction_ids.*.exists' => 'One or more pending transactions do not belong to this ledger.',
        ];
    }
}
