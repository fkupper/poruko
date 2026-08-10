<?php

namespace Tests\Unit\Services;

use App\Modules\Ledger\Services\TransactionSplitService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(TransactionSplitService::class)]
class TransactionSplitServiceTest extends TestCase
{
    public function testAllocateEqualSplitsEvenly(): void
    {
        // Arrange
        $service = new TransactionSplitService();

        // Act
        $allocations = $service->allocateByRule(
            'equal',
            1000,
            [1, 2],
            [],
            [],
        );

        // Assert
        $this->assertSame([1 => 500, 2 => 500], $allocations);
        $this->assertSame(1000, array_sum($allocations));
    }

    public function testAllocateEqualGivesRemainderToLastParticipant(): void
    {
        // Arrange
        $service = new TransactionSplitService();

        // Act
        $allocations = $service->allocateByRule(
            'equal',
            1001,
            [10, 20, 30],
            [],
            [],
        );

        // Assert
        $this->assertSame([10 => 333, 20 => 333, 30 => 335], $allocations);
        $this->assertSame(1001, array_sum($allocations));
    }

    public function testAllocateProportionalUsesShareableIncome(): void
    {
        // Arrange
        $service = new TransactionSplitService();

        // Act
        $allocations = $service->allocateByRule(
            'proportional',
            10_000,
            [1, 2],
            [],
            [1 => 30_000, 2 => 70_000],
        );

        // Assert
        $this->assertSame([1 => 3000, 2 => 7000], $allocations);
        $this->assertSame(10_000, array_sum($allocations));
    }

    public function testAllocateProportionalWithZeroIncomeFallsBackToEqual(): void
    {
        // Arrange
        $service = new TransactionSplitService();

        // Act
        $allocations = $service->allocateByRule(
            'proportional',
            1001,
            [1, 2],
            [],
            [1 => 0, 2 => 0],
        );

        // Assert — equal fallback; last participant gets remainder
        $this->assertSame([1 => 500, 2 => 501], $allocations);
        $this->assertSame(1001, array_sum($allocations));
    }

    public function testAllocateManualGivesRemainderToLastParticipant(): void
    {
        // Arrange
        $service = new TransactionSplitService();

        // Act
        $allocations = $service->allocateByRule(
            'manual',
            1000,
            [1, 2],
            [
                ['user_id' => 1, 'share' => 1],
                ['user_id' => 2, 'share' => 2],
            ],
            [],
        );

        // Assert
        $this->assertSame([1 => 333, 2 => 667], $allocations);
        $this->assertSame(1000, array_sum($allocations));
    }

    public function testAllocateManualWithEmptyParticipantsReturnsEmpty(): void
    {
        // Arrange
        $service = new TransactionSplitService();

        // Act
        $allocations = $service->allocateByRule(
            'manual',
            1000,
            [],
            [],
            [],
        );

        // Assert
        $this->assertSame([], $allocations);
    }

    public function testAllocateIndividualAssignsFullAmountToSingleParticipant(): void
    {
        // Arrange
        $service = new TransactionSplitService();

        // Act
        $allocations = $service->allocateByRule(
            'individual',
            2500,
            [42],
            [['user_id' => 42]],
            [],
        );

        // Assert
        $this->assertSame([42 => 2500], $allocations);
    }
}
