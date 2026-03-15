<?php

namespace Tests\Unit\Services;

use App\Models\Ledger;
use App\Modules\Ledger\Services\SettlementSafetyGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(SettlementSafetyGateService::class)]
class SettlementSafetyGateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsAutoAllowedFalseWhenAutoExecuteDisabled(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_auto_execute_enabled' => false,
        ]);
        $service = $this->app->make(SettlementSafetyGateService::class);

        $result = $service->evaluate($ledger, ['required_transfers' => []]);

        $this->assertFalse($result['auto_allowed']);
        $this->assertSame('auto_execute_disabled', $result['reason']);
    }

    public function testItReturnsAutoAllowedTrueWhenTransfersWithinLimits(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_auto_execute_enabled' => true,
        ]);
        $service = $this->app->make(SettlementSafetyGateService::class);

        $result = $service->evaluate($ledger, [
            'required_transfers' => [
                ['amount' => 100000],
                ['amount' => 200000],
            ],
        ]);

        $this->assertTrue($result['auto_allowed']);
        $this->assertNull($result['reason']);
    }

    public function testItReturnsAutoAllowedFalseWhenTooManyTransfers(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_auto_execute_enabled' => true,
        ]);
        $service = $this->app->make(SettlementSafetyGateService::class);
        $transfers = array_fill(0, 21, ['amount' => 1000]);

        $result = $service->evaluate($ledger, ['required_transfers' => $transfers]);

        $this->assertFalse($result['auto_allowed']);
        $this->assertSame('too_many_transfers', $result['reason']);
    }

    public function testItReturnsAutoAllowedFalseWhenTransferAmountExceedsThreshold(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_auto_execute_enabled' => true,
        ]);
        $service = $this->app->make(SettlementSafetyGateService::class);

        $result = $service->evaluate($ledger, [
            'required_transfers' => [
                ['amount' => 500001],
            ],
        ]);

        $this->assertFalse($result['auto_allowed']);
        $this->assertSame('transfer_amount_exceeds_threshold', $result['reason']);
    }

    public function testItReturnsAutoAllowedTrueWhenNoTransfers(): void
    {
        $ledger = Ledger::factory()->create([
            'settlement_auto_execute_enabled' => true,
        ]);
        $service = $this->app->make(SettlementSafetyGateService::class);

        $result = $service->evaluate($ledger, []);

        $this->assertTrue($result['auto_allowed']);
        $this->assertNull($result['reason']);
    }
}
