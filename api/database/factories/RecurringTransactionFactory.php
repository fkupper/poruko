<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionSplitRule;
use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ledger = Ledger::factory()->create();

        return [
            'ledger_id' => $ledger->id,
            'credit_account_id' => fn (array $attributes) => Account::factory()->create([
                'ledger_id' => $attributes['ledger_id'] ?? $ledger->id,
            ])->id,
            'debit_account_id' => fn (array $attributes) => Account::factory()->create([
                'ledger_id' => $attributes['ledger_id'] ?? $ledger->id,
            ])->id,
            'amount' => fake()->numberBetween(1000, 100_000),
            'description' => fake()->sentence(),
            'split_rule' => TransactionSplitRule::Equal->value,
            'participants' => [],
            'frequency' => RecurringFrequency::Monthly->value,
            'valid_from' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'valid_to' => null,
        ];
    }
}
