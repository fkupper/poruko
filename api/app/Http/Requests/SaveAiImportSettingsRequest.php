<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAiImportSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');
        $user = $this->user();

        return $ledger instanceof Ledger
            && $user instanceof User
            && $user->can('view', $ledger)
            && $user->can('ai_ingestion');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasProviderSetting = $this->user()?->aiProviderSetting()->exists() === true;

        return [
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic'])],
            'api_key' => [
                Rule::requiredIf(!$hasProviderSetting),
                'nullable',
                'string',
                'min:12',
                'max:500',
            ],
            'model' => ['nullable', 'string', 'max:100'],
            'auto_create_accounts' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'api_key.required' => 'An API key is required the first time a provider is configured.',
            'provider.in' => 'The selected AI provider is not supported.',
        ];
    }
}
