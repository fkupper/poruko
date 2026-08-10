<?php

namespace Database\Factories;

use App\Enums\SettlementMode;
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
            'currency' => 'USD',
            'settlement_mode' => fake()->randomElement(SettlementMode::cases())->value,
            'settlement_timezone' => fake()->timezone(),
            'settlement_cutoff_day' => fake()->numberBetween(1, 28),
            'settlement_cutoff_time' => fake()->time('H:i:s'),
            'settlement_auto_execute_enabled' => fake()->boolean(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Ledger $ledger) {
            \App\Models\Account::factory()->create([
                'ledger_id' => $ledger->id,
                'name' => 'Space Expense',
                'type' => \App\Enums\AccountType::SpaceExpense->value,
                'owner_id' => null,
            ]);
            \App\Models\Account::factory()->create([
                'ledger_id' => $ledger->id,
                'name' => 'Split Clearing',
                'type' => \App\Enums\AccountType::SplitClearing->value,
                'owner_id' => null,
            ]);
        });
    }
}
