<?php

namespace Database\Factories;

use App\Enums\McpOperation;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\McpActionLog>
 */
class McpActionLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ledger_id' => Ledger::factory(),
            'tool_name' => 'list-accounts',
            'operation' => McpOperation::Read->value,
            'request_payload' => ['ledger_id' => 1],
            'response_status' => 'ok',
            'duration_ms' => 12,
            'created_at' => now(),
        ];
    }
}
