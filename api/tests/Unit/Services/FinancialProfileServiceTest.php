<?php

namespace Tests\Unit\Services;

use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Models\User;
use App\Modules\Ledger\Services\FinancialProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(FinancialProfileService::class)]
class FinancialProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testUpsertUpdatesActiveProfileInPlaceWhenPeriodNotLocked(): void
    {
        Carbon::setTestNow('2026-03-15');
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        $existing = FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => '2026-03-01',
            'valid_to' => null,
            'incomes' => [['description' => 'Old', 'amount' => 100000]],
            'deductions' => [],
        ]);

        $service = $this->app->make(FinancialProfileService::class);
        $updated = $service->upsertActive(
            $ledger,
            $user,
            [['description' => 'New', 'amount' => 120000]],
            [['description' => 'Tax', 'amount' => 20000]],
        );

        $this->assertSame($existing->id, $updated->id);
        $this->assertDatabaseCount('financial_profiles', 1);
    }

    public function testUpsertBranchesProfileWhenCurrentPeriodIsLockedBySettlement(): void
    {
        Carbon::setTestNow('2026-03-15');
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        $existing = FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => '2026-03-01',
            'valid_to' => null,
            'incomes' => [['description' => 'Old', 'amount' => 100000]],
            'deductions' => [],
        ]);

        Settlement::factory()->create([
            'ledger_id' => $ledger->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'executed_at' => '2026-03-31 12:00:00',
        ]);

        $service = $this->app->make(FinancialProfileService::class);
        $branched = $service->upsertActive(
            $ledger,
            $user,
            [['description' => 'Branched', 'amount' => 150000]],
            [['description' => 'Tax', 'amount' => 25000]],
        );

        $this->assertNotSame($existing->id, $branched->id);
        $this->assertSame('2026-04-01', $branched->valid_from?->format('Y-m-d'));
        $this->assertDatabaseHas('financial_profiles', [
            'id' => $existing->id,
            'valid_to' => '2026-03-31',
        ]);
    }

    public function testUpsertCreatesNewProfileWhenNoneExists(): void
    {
        Carbon::setTestNow('2026-03-15');
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        $service = $this->app->make(FinancialProfileService::class);
        $profile = $service->upsertActive(
            $ledger,
            $user,
            [['description' => 'Salary', 'amount' => 200000]],
            [['description' => 'Tax', 'amount' => 20000]],
        );

        $this->assertDatabaseCount('financial_profiles', 1);
        $this->assertSame($ledger->id, $profile->ledger_id);
        $this->assertSame($user->id, $profile->user_id);
        $this->assertSame('2026-03-01', $profile->valid_from?->format('Y-m-d'));
        $this->assertNull($profile->valid_to);
    }

    public function testShareableIncomeOnReturnsZeroWhenNoProfileExists(): void
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        $service = $this->app->make(FinancialProfileService::class);
        $income = $service->shareableIncomeOn($ledger, $user, '2026-03-15');

        $this->assertSame(0, $income);
    }

    public function testShareableIncomeOnReturnsComputedValueWhenProfileExists(): void
    {
        Carbon::setTestNow('2026-03-15');
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => '2026-03-01',
            'valid_to' => null,
            'incomes' => [['description' => 'Salary', 'amount' => 100000]],
            'deductions' => [['description' => 'Tax', 'amount' => 15000]],
        ]);

        $service = $this->app->make(FinancialProfileService::class);
        $income = $service->shareableIncomeOn($ledger, $user, '2026-03-15');

        $this->assertSame(85000, $income);
    }

    public function testShareableIncomeForUsersReturnsEmptyWhenNoUserIds(): void
    {
        $ledger = Ledger::factory()->create();

        $service = $this->app->make(FinancialProfileService::class);
        $result = $service->shareableIncomeForUsers($ledger, [], '2026-03-15');

        $this->assertSame([], $result);
    }

    public function testShareableIncomeForUsersReturnsZeroForUsersWithoutProfile(): void
    {
        $ledger = Ledger::factory()->create();
        $userWithProfile = User::factory()->create();
        $userWithoutProfile = User::factory()->create();

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $userWithProfile->id,
            'valid_from' => '2026-03-01',
            'valid_to' => null,
            'incomes' => [['description' => 'Salary', 'amount' => 50000]],
            'deductions' => [],
        ]);

        $service = $this->app->make(FinancialProfileService::class);
        $result = $service->shareableIncomeForUsers(
            $ledger,
            [$userWithProfile->id, $userWithoutProfile->id],
            '2026-03-15',
        );

        $this->assertSame(50000, $result[$userWithProfile->id]);
        $this->assertSame(0, $result[$userWithoutProfile->id]);
    }
}
