<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Modules\Ledger\Actions\PreviewSettlementAction;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ShowSettlementBreakdownCommand extends Command
{
    protected $signature = 'settlements:show {settlement : The settlement ID}';

    protected $description = 'Display a breakdown of a settlement (summary, user balances, required transfers)';

    public function __construct(
        private readonly PreviewSettlementAction $previewSettlementAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $settlementId = (int) $this->argument('settlement');

        $settlement = Settlement::query()
            ->with('ledger')
            ->find($settlementId);

        if ($settlement === null) {
            $this->error('Settlement not found.');

            return self::FAILURE;
        }

        $periodEnd = Carbon::parse($settlement->period_end)->format('Y-m-d');
        $preview = $this->previewSettlementAction->executeForPeriodEnd($settlement->ledger, $periodEnd);

        $this->renderHeader($settlement, $preview);
        $this->renderSummary($preview['summary']);
        $this->renderSourceTransactions($settlement->ledger_id, $preview['period_start'], $preview['period_end']);
        $this->renderUserBreakdowns($preview['user_breakdowns']);
        $this->renderRequiredTransfers($preview['required_transfers'], $settlement->ledger_id);

        return self::SUCCESS;
    }

    /**
     * @param array{period_start:string, period_end:string, settlement_mode:string} $preview
     */
    private function renderHeader(Settlement $settlement, array $preview): void
    {
        $executed = $settlement->executed_at !== null
            ? Carbon::parse($settlement->executed_at)->format('Y-m-d H:i:s')
            : 'Pending';

        $this->line("Settlement #{$settlement->id}");
        $this->line("Ledger: {$settlement->ledger->name}");
        $this->line("Period: {$preview['period_start']} → {$preview['period_end']}");
        $this->line("Mode: {$preview['settlement_mode']}");
        $this->line("Executed: {$executed}");
        $this->newLine();
    }

    /**
     * @param array{total_shared_spend:int, pool_current_balance:int} $summary
     */
    private function renderSummary(array $summary): void
    {
        $this->table(
            ['Metric', 'Amount'],
            [
                ['Total shared spend', $this->formatCents($summary['total_shared_spend'])],
                ['Pool current balance', $this->formatCents($summary['pool_current_balance'])],
            ],
        );
        $this->newLine();
    }

    private function renderSourceTransactions(int $ledgerId, string $periodStart, string $periodEnd): void
    {
        $transactions = Transaction::query()
            ->with(['creditAccount', 'debitAccount'])
            ->where('ledger_id', $ledgerId)
            ->where('type', '!=', 'settlement')
            ->betweenDates($periodStart, $periodEnd)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            $this->line('No source transactions in this period.');
            $this->newLine();

            return;
        }

        $rows = [];

        foreach ($transactions as $tx) {
            $splitRule = $tx->split_rule instanceof BackedEnum
                ? $tx->split_rule->value
                : (string) $tx->split_rule;

            $rows[] = [
                Carbon::parse($tx->date)->format('Y-m-d'),
                $tx->description ?? '-',
                $tx->creditAccount->name ?? 'Account #' . $tx->credit_account_id,
                $tx->debitAccount->name ?? 'Account #' . $tx->debit_account_id,
                $this->formatCents((int) $tx->amount),
                $splitRule,
            ];
        }

        $this->table(
            ['Date', 'Description', 'Credit', 'Debit', 'Amount', 'Split rule'],
            $rows,
        );
        $this->newLine();
    }

    /**
     * @param array<int, array{user_id:int, name:string, active_ratio:float, target_liability:int, paid_out_of_pocket:int, net_balance:int}> $userBreakdowns
     */
    private function renderUserBreakdowns(array $userBreakdowns): void
    {
        $rows = [];

        foreach ($userBreakdowns as $row) {
            $rows[] = [
                $row['name'],
                (string) ($row['active_ratio'] * 100) . '%',
                $this->formatCents($row['target_liability']),
                $this->formatCents($row['paid_out_of_pocket']),
                $this->formatCents($row['net_balance']),
            ];
        }

        $this->table(
            ['Name', 'Active ratio', 'Target liability', 'Paid out of pocket', 'Net balance'],
            $rows,
        );
        $this->newLine();
    }

    /**
     * @param array<int, array{from_account_id:int, to_account_id:int, amount:int, instruction:string}> $transfers
     */
    private function renderRequiredTransfers(array $transfers, int $ledgerId): void
    {
        if (count($transfers) === 0) {
            $this->line('No transfers required.');

            return;
        }

        $accountIds = [];

        foreach ($transfers as $t) {
            $accountIds[] = $t['from_account_id'];
            $accountIds[] = $t['to_account_id'];
        }
        $accountIds = array_unique($accountIds);

        $accounts = Account::query()
            ->where('ledger_id', $ledgerId)
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($transfers as $t) {
            $fromName = $accounts->get($t['from_account_id'])->name ?? 'Account #' . $t['from_account_id'];
            $toName = $accounts->get($t['to_account_id'])->name ?? 'Account #' . $t['to_account_id'];

            $rows[] = [
                "{$fromName} → {$toName}",
                $this->formatCents($t['amount']),
                $t['instruction'],
            ];
        }

        $this->table(
            ['From → To', 'Amount', 'Instruction'],
            $rows,
        );
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2) . ' €';
    }
}
