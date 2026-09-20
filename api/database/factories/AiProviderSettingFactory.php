<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AiProviderSetting>
 */
class AiProviderSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $apiKey = 'test-' . fake()->sha256();

        return [
            'user_id' => User::factory(),
            'provider' => AiProvider::OpenAi,
            'base_url' => null,
            'api_key' => $apiKey,
            'api_key_last_four' => mb_substr($apiKey, -4),
            'model' => 'gpt-4.1-mini',
        ];
    }

    public function openaiCompatible(?string $baseUrl = 'http://127.0.0.1:11434/v1'): static
    {
        return $this->state(function () use ($baseUrl): array {
            $apiKey = 'local-key';

            return [
                'provider' => AiProvider::OpenAiCompatible,
                'base_url' => $baseUrl,
                'api_key' => $apiKey,
                'api_key_last_four' => mb_substr($apiKey, -4),
                'model' => 'llama3.1',
            ];
        });
    }
}
