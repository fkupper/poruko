<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExecuteSettlementJob;
use App\Models\Account;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\ExecuteSettlementAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ledger')]
#[CoversClass(ExecuteSettlementJob::class)]
class ExecuteSettlementJobTest extends TestCase
{
    use RefreshDatabase;

    public function testHandleResolvesLedgerAndCallsAction(): void
    {
        // Arrange
        $ledger = Ledger::factory()->create();
        $periodEnd = '2026-02-28';

        $action = $this->createMock(ExecuteSettlementAction::class);
        $action->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(fn ($arg) => $arg instanceof Ledger && $arg->id === $ledger->id),
                $periodEnd,
            );

        $this->app->instance(ExecuteSettlementAction::class, $action);

        $job = new ExecuteSettlementJob($ledger->id, $periodEnd);

        // Act
        $job->handle($this->app->make(ExecuteSettlementAction::class));
    }

    public function testHandleUsesRealActionAndExecutesSettlement(): void
    {
        // Arrange - minimal ledger with transactions so action can run
        $ledger = Ledger::factory()->create([
            'settlement_mode' => 'direct_p2p',
            'settlement_timezone' => 'UTC',
            'settlement_cutoff_day' => 31,
            'settlement_cutoff_time' => '23:59:00',
        ]);
        $user = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $credit = Account::query()->findOrFail(
            DB::table('ledger_user')
                ->where('ledger_id', $ledger->id)
                ->where('user_id', $user->id)
                ->value('main_personal_account_id'),
        );
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
            'date' => '2026-02-15',
        ]);

        $periodEnd = '2026-02-28';
        $job = new ExecuteSettlementJob($ledger->id, $periodEnd);

        // Act
        $job->handle($this->app->make(ExecuteSettlementAction::class));

        // Assert
        $this->assertDatabaseHas('settlements', [
            'ledger_id' => $ledger->id,
            'period_end' => $periodEnd,
        ]);
    }
}
