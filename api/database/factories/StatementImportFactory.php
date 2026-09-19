<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\StatementImport>
 */
class StatementImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'ledger_id' => Ledger::factory(),
            'user_id' => User::factory(),
            'status' => 'queued',
            'file_path' => 'statement-imports/example.csv',
            'original_filename' => 'statement.csv',
            'mime_type' => 'text/csv',
            'file_size' => 1024,
            'parsed_count' => 0,
            'pending_count' => 0,
            'duplicate_count' => 0,
            'failed_count' => 0,
            'error_message' => null,
            'processed_at' => null,
        ];
    }
}
