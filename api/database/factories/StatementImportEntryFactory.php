<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\StatementImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StatementImportEntry>
 */
class StatementImportEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statement_import_id' => StatementImport::factory(),
            'ledger_id' => Ledger::factory(),
            'transaction_fingerprint' => fake()->sha256(),
            'status' => 'pending',
            'raw_data' => [
                'date' => fake()->date(),
                'description' => fake()->company(),
                'amount_cents' => fake()->numberBetween(100, 50_000),
            ],
            'pending_transaction_id' => null,
        ];
    }
}
