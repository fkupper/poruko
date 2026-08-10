<?php

namespace App\Http\Requests;

use App\Enums\SettlementMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['nullable', 'string', Rule::in(array_keys(config('currencies.available', [])))],
            'settlement_mode' => ['nullable', 'string', Rule::enum(SettlementMode::class)],
            'settlement_cutoff_day' => ['nullable', 'integer', 'between:1,31'],
            'settlement_timezone' => ['nullable', 'string', 'timezone'],
            'settlement_auto_execute_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A space name is required.',
            'name.max' => 'The space name must not exceed 255 characters.',
            'settlement_mode.enum' => 'Selected settlement mode is invalid.',
        ];
    }
}
