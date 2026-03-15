<?php

namespace Tests\Feature\Console;

use App\Jobs\ExecuteSettlementJob;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('settlements')]
class SettlementSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function testDispatchesJobsForLedgersWithAutoExecuteEnabled(): void
    {
        Bus::fake();

        Carbon::setTestNow('2026-03-31 23:59:10');

        $enabled = Ledger::factory()->create([
            'settlement_auto_execute_enabled' => true,
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);
        $this->seedMinimalSettlementContext($enabled);

        $disabled = Ledger::factory()->create([
            'settlement_auto_execute_enabled' => false,
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);
        $this->seedMinimalSettlementContext($disabled);

        $this->artisan('settlements:run-due')
            ->assertExitCode(0);

        Bus::assertDispatched(ExecuteSettlementJob::class, function (ExecuteSettlementJob $job) use ($enabled): bool {
            return $job->ledgerId === $enabled->id;
        });

        $this->assertDatabaseHas('settlements', [
            'ledger_id' => $disabled->id,
            'period_end' => '2026-02-28',
            'executed_at' => null,
        ]);
    }

    public function testSchedulerSkipsWhenPeriodAlreadyTracked(): void
    {
        Bus::fake();
        Carbon::setTestNow('2026-03-31 23:59:10');

        $ledger = Ledger::factory()->create([
            'settlement_auto_execute_enabled' => true,
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);
        $this->seedMinimalSettlementContext($ledger);

        Settlement::factory()->create([
            'ledger_id' => $ledger->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'executed_at' => null,
        ]);

        $this->artisan('settlements:run-due')->assertExitCode(0);

        Bus::assertNotDispatched(ExecuteSettlementJob::class);
    }

    public function testSchedulerCreatesPreviousMonthPeriodForDayOneCutoff(): void
    {
        Bus::fake();
        Carbon::setTestNow('2026-04-01 00:00:01');

        $ledger = Ledger::factory()->create([
            'settlement_auto_execute_enabled' => false,
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 1,
            'settlement_cutoff_time' => '00:00:00',
        ]);
        $this->seedMinimalSettlementContext($ledger);

        $this->artisan('settlements:run-due')->assertExitCode(0);

        $this->assertDatabaseHas('settlements', [
            'ledger_id' => $ledger->id,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'executed_at' => null,
        ]);
        Bus::assertNotDispatched(ExecuteSettlementJob::class);
    }

    /*
     * Seeders.
     */

    private function seedMinimalSettlementContext(Ledger $ledger): void
    {
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $credit = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => 'personal',
        ]);
        $debit = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'type' => 'external',
            'owner_id' => null,
        ]);

        FinancialProfile::factory()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => '2026-01-01',
            'incomes' => [['description' => 'Income', 'amount' => 100000]],
            'deductions' => [],
        ]);

        Transaction::factory()->create([
            'ledger_id' => $ledger->id,
            'credit_account_id' => $credit->id,
            'debit_account_id' => $debit->id,
            'amount' => 10000,
            'type' => 'manual',
            'split_rule' => 'equal',
            'participants' => [],
            'date' => '2026-03-15',
        ]);
    }
}
