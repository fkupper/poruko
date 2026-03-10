<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:personal,pool,external'],
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
