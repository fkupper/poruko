<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\Settlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Settlement>
 */
class SettlementFactory extends Factory
{
    protected $model = Settlement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodEnd = fake()->date();
        $periodStart = date('Y-m-01', strtotime($periodEnd));

        return [
            'ledger_id' => Ledger::factory(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'executed_at' => null,
        ];
    }
}
