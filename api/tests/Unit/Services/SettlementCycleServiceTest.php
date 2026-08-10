<?php

namespace Tests\Unit\Services;

use App\Models\Ledger;
use App\Modules\Ledger\Services\SettlementCycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(SettlementCycleService::class)]
class SettlementCycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testResolvePeriodForDateUsesPreviousCalendarMonth(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 1,
            'settlement_cutoff_time' => '00:00:00',
        ]);

        $service = $this->app->make(SettlementCycleService::class);
        $period = $service->resolvePeriodForDate($ledger, '2026-04-01');

        $this->assertSame('2026-04-01', $period['period_start']);
        $this->assertSame('2026-04-30', $period['period_end']);
    }

    public function testResolvePeriodForDateHandlesLeapYearMonthEnd(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 15,
            'settlement_cutoff_time' => '10:00:00',
        ]);

        $service = $this->app->make(SettlementCycleService::class);
        $period = $service->resolvePeriodForDate($ledger, '2024-03-10');

        $this->assertSame('2024-03-01', $period['period_start']);
        $this->assertSame('2024-03-31', $period['period_end']);
    }

    public function testResolveDuePeriodEndReturnsNullBeforeCutoff(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 10,
            'settlement_cutoff_time' => '12:00:00',
        ]);

        $service = $this->app->make(SettlementCycleService::class);
        $duePeriodEnd = $service->resolveDuePeriodEnd(
            $ledger,
            CarbonImmutable::parse('2026-04-10 11:59:59', 'UTC'),
        );

        $this->assertNull($duePeriodEnd);
    }

    public function testResolveDuePeriodEndReturnsPreviousMonthEndOnceCutoffIsReached(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 1,
            'settlement_cutoff_time' => '00:00:00',
        ]);

        $service = $this->app->make(SettlementCycleService::class);
        $duePeriodEnd = $service->resolveDuePeriodEnd(
            $ledger,
            CarbonImmutable::parse('2026-04-01 00:00:00', 'UTC'),
        );

        $this->assertSame('2026-03-31', $duePeriodEnd);
    }

    public function testResolvePeriodForPeriodEndNormalizesToCalendarMonthBounds(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);

        $service = $this->app->make(SettlementCycleService::class);
        $period = $service->resolvePeriodForPeriodEnd($ledger, '2026-03-12');

        $this->assertSame('2026-03-01', $period['period_start']);
        $this->assertSame('2026-03-31', $period['period_end']);
    }
}
