<?php

namespace Database\Factories;

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
            'provider' => 'openai',
            'api_key' => $apiKey,
            'api_key_last_four' => mb_substr($apiKey, -4),
            'model' => 'gpt-4.1-mini',
        ];
    }
}
