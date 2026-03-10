<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'owner_id' => User::factory(),
            'type' => fake()->randomElement(['personal', 'pool', 'external']),
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->bothify('ACC-#####')),
        ];
    }
}
