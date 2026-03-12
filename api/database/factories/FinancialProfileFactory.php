<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialProfile>
 */
class FinancialProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'user_id' => User::factory(),
            'valid_from' => now()->startOfMonth()->format('Y-m-d'),
            'valid_to' => null,
            'incomes' => [
                ['description' => 'Salary', 'amount' => fake()->numberBetween(200000, 600000)],
            ],
            'deductions' => [
                ['description' => 'Health Insurance', 'amount' => fake()->numberBetween(10000, 30000)],
            ],
        ];
    }
}
