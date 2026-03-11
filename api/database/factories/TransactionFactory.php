<?php

namespace Database\Factories;

use App\Enums\TransactionSplitRule;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
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
            'payer_account_id' => Account::factory(),
            'amount' => fake()->numberBetween(100, 100_000),
            'type' => TransactionType::Manual->value,
            'split_rule' => fake()->randomElement([
                TransactionSplitRule::Equal->value,
                TransactionSplitRule::Individual->value,
            ]),
            'participants' => [
                [
                    'account_id' => fake()->numberBetween(1, 1000),
                    'amount' => fake()->numberBetween(100, 10_000),
                ],
            ],
            'description' => fake()->sentence(),
            'date' => fake()->date(),
        ];
    }
}
