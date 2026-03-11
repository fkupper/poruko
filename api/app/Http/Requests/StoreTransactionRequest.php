<?php

namespace App\Http\Requests;

use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('create', [Transaction::class, $ledger]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payer_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'type' => ['nullable', Rule::in([TransactionType::Manual->value])],
            'split_rule' => ['required', new Enum(TransactionSplitRule::class)],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'participants.*.amount' => ['required_if:split_rule,individual', 'integer', 'min:0'],
        ];
    }
}
