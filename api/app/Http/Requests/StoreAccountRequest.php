<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('create', [Account::class, $ledger]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ledger = $this->route('ledger');
        $ledgerId = $ledger instanceof Ledger ? $ledger->id : 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(AccountType::class)],
            'owner_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::exists('ledger_user', 'user_id')->where('ledger_id', $ledgerId),
            ],
            'base_budget' => ['nullable', 'integer', 'min:0'],
            'balance' => ['nullable', 'integer', 'min:0'],
            'code' => ['nullable', 'string', 'max:255', 'unique:accounts,code'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The account name is required.',
            'type.required' => 'The account type is required.',
            'owner_id.exists' => 'The selected owner does not exist or is not a member of this ledger.',
            'code.unique' => 'This account code is already in use.',
        ];
    }
}
