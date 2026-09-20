<?php

namespace App\Http\Requests;

use App\Enums\AiProvider;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Services\OpenAiCompatibleEndpoint;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

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
        $isCompatible = $this->input('provider') === AiProvider::OpenAiCompatible->value;

        return [
            'provider' => ['required', 'string', Rule::enum(AiProvider::class)],
            'api_key' => [
                Rule::requiredIf(!$hasProviderSetting),
                'nullable',
                'string',
                Rule::when($isCompatible, ['min:4'], ['min:12']),
                'max:500',
            ],
            'model' => [
                Rule::requiredIf($isCompatible),
                'nullable',
                'string',
                'max:100',
            ],
            'base_url' => [
                Rule::requiredIf($isCompatible),
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail) use ($isCompatible): void {
                    if (!$isCompatible || !is_string($value) || $value === '') {
                        return;
                    }

                    try {
                        OpenAiCompatibleEndpoint::assertValidBaseUrl($value);
                    } catch (InvalidArgumentException $exception) {
                        $fail($exception->getMessage());
                    }
                },
            ],
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
            'model.required' => 'A model name is required for OpenAI-compatible and self-hosted endpoints.',
            'base_url.required' => 'A base URL is required for OpenAI-compatible and self-hosted endpoints.',
            'provider.enum' => 'The selected AI provider is not supported.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'api_key' => $this->trimmed('api_key'),
            'model' => $this->trimmed('model'),
            'base_url' => $this->trimmed('base_url'),
        ]);
    }

    private function trimmed(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $value;
    }
}
