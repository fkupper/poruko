<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\TransactionSplitRule;
use App\Enums\TransactionSource;
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
            'payer_account_id' => fn (array $attributes) => Account::factory()->create([
                'ledger_id' => $attributes['ledger_id'],
            ])->id,
            'destination_account_id' => fn (array $attributes) => Account::factory()->create([
                'ledger_id' => $attributes['ledger_id'],
                'type' => AccountType::SpaceExpense,
            ])->id,
            'amount' => fake()->numberBetween(100, 100_000),
            'type' => TransactionType::Manual->value,
            'source' => TransactionSource::Manual->value,
            'source_metadata' => null,
            'split_rule' => fake()->randomElement([
                TransactionSplitRule::Equal->value,
                TransactionSplitRule::Individual->value,
                TransactionSplitRule::Proportional->value,
                TransactionSplitRule::Manual->value,
            ]),
            'participants' => [],
            'description' => fake()->sentence(),
            'date' => fake()->date(),
        ];
    }
}
