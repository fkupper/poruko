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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', new Enum(AccountType::class)],
            'owner_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('accounts', 'code')->ignore($this->route('account')?->id),
            ],
        ];
    }
}
