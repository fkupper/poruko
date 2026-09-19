<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BankAccountMapping>
 */
class BankAccountMappingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'user_id' => User::factory(),
            'external_account_fingerprint' => fake()->sha256(),
            'external_account_name' => fake()->words(2, true),
            'masked_identifier' => '•••• ' . fake()->numerify('####'),
            'ownership_type' => 'personal',
            'account_id' => null,
            'suggested_account_id' => null,
        ];
    }
}
