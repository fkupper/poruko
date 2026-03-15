<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account
            && $this->user()?->can('update', $account) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->route('account');
        $codeRules = ['sometimes', 'nullable', 'string', 'max:255'];
        $ledgerId = $account instanceof Account ? $account->ledger_id : 0;

        if ($account instanceof Account) {
            $codeRules[] = Rule::unique('accounts', 'code')->ignore($account->id);
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', new Enum(AccountType::class)],
            'owner_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id',
                Rule::exists('ledger_user', 'user_id')->where('ledger_id', $ledgerId),
            ],
            'code' => $codeRules,
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
