<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ledger>
 */
class LedgerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'settlement_mode' => fake()->randomElement(['joint_clearinghouse', 'direct_p2p']),
            'pool_base_budget' => fake()->numberBetween(0, 1_000_000),
        ];
    }
}
