<?php

namespace Database\Factories;

use App\Enums\AccountType;
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
        $type = fake()->randomElement([
            AccountType::Personal->value,
            AccountType::Pool->value,
            AccountType::External->value,
        ]);

        return [
            'ledger_id' => Ledger::factory(),
            'owner_id' => in_array($type, [AccountType::Pool->value, AccountType::External->value], true)
                ? null
                : User::factory(),
            'type' => $type,
            'name' => fake()->words(2, true),
            'code' => mb_strtoupper(fake()->unique()->bothify('ACC-#####')),
            'base_budget' => 0,
        ];
    }
}
