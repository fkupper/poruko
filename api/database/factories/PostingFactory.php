<?php

namespace Database\Factories;

use App\Enums\PostingDirection;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Posting>
 */
class PostingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'account_id' => Account::factory(),
            'amount' => fake()->numberBetween(1, 50_000),
            'direction' => PostingDirection::Credit->value,
        ];
    }

    /**
     * Create a credit posting for the transaction's payer account.
     */
    public function forCreditAccount(Transaction $transaction): static
    {
        return $this->state(fn () => [
            'transaction_id' => $transaction->id,
            'account_id' => $transaction->payer_account_id,
            'amount' => $transaction->amount,
            'direction' => PostingDirection::Credit->value,
        ]);
    }

    /**
     * Create a debit posting for the transaction's destination account.
     */
    public function forDebitAccount(Transaction $transaction): static
    {
        return $this->state(fn () => [
            'transaction_id' => $transaction->id,
            'account_id' => $transaction->destination_account_id,
            'amount' => $transaction->amount,
            'direction' => PostingDirection::Debit->value,
        ]);
    }
}
