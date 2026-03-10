<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'type' => ['nullable', 'string', 'in:manual,recurring,settlement,reversal'],
            'split_rule' => ['required', 'string', 'in:equal,individual'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'participants.*.amount' => ['required_if:split_rule,individual', 'integer', 'min:0'],
        ];
    }
}
