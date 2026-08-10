<?php

namespace App\Http\Requests;

use App\Enums\TransactionSplitRule;
use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('view', $ledger) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ledger = $this->route('ledger');
        $ledgerId = $ledger instanceof Ledger ? $ledger->id : null;

        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('ledger_id', $ledgerId),
            ],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => [
                'integer',
                Rule::exists('accounts', 'id')->where('ledger_id', $ledgerId),
            ],
            'creator_user_ids' => ['nullable', 'array'],
            'creator_user_ids.*' => ['integer'],
            'split_rules' => ['nullable', 'array'],
            'split_rules.*' => ['string', Rule::enum(TransactionSplitRule::class)],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in(['manual', 'recurring', 'settlement', 'reversal'])],
            'settlement_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_date.after_or_equal' => 'The end date must be on or after the start date.',
            'account_id.exists' => 'The selected account does not exist in this ledger.',
            'per_page.max' => 'per_page may not be greater than 100.',
        ];
    }
}
