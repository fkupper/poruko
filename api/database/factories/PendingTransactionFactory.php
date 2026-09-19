<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\PendingTransactionStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionSplitRule;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PendingTransaction>
 */
class PendingTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'user_id' => User::factory(),
            'payer_account_id' => fn (array $attributes) => Account::factory()->create([
                'ledger_id' => $attributes['ledger_id'],
            ])->id,
            'destination_account_id' => fn (array $attributes) => Account::factory()->create([
                'ledger_id' => $attributes['ledger_id'],
                'type' => AccountType::SpaceExpense,
            ])->id,
            'raw_data' => [
                'description' => fake()->sentence(),
            ],
            'suggested_description' => fake()->sentence(),
            'suggested_amount' => fake()->numberBetween(100, 100_000),
            'suggested_split_rule' => TransactionSplitRule::Equal->value,
            'suggested_participants' => [],
            'date' => fake()->date(),
            'source' => TransactionSource::AiImport->value,
            'confidence' => fake()->randomFloat(4, 0, 1),
            'rationale' => fake()->sentence(),
            'status' => PendingTransactionStatus::Pending->value,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PendingTransactionStatus::Approved->value,
            'reviewed_by_user_id' => User::factory(),
            'reviewed_at' => now(),
            'committed_transaction_id' => Transaction::factory()->create([
                'ledger_id' => $attributes['ledger_id'],
            ])->id,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => PendingTransactionStatus::Rejected->value,
            'reviewed_by_user_id' => User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => 'Rejected during review.',
        ]);
    }
}
