<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\AccountType;
use App\Enums\SettlementMode;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Modules\Ledger\Services\FinancialProfileService;
use App\Modules\Ledger\Services\SettlementCycleService;
use BackedEnum;
use Illuminate\Support\Collection;

readonly class PreviewSettlementAction
{
    public function __construct(
        private readonly FinancialProfileService $financialProfileService,
        private readonly SettlementCycleService $settlementCycleService,
    ) {}

    /**
     * @return array{
     *   ledger_id:int,
     *   period_start:string,
     *   period_end:string,
     *   settlement_mode:string,
     *   summary:array{total_shared_spend:int, pool_current_balance:int},
     *   user_breakdowns:array<int, array{
     *      user_id:int,
     *      name:string,
     *      active_ratio:float,
     *      target_liability:int,
     *      paid_out_of_pocket:int,
     *      net_balance:int
     *   }>,
     *   required_transfers:array<int, array{
     *      from_account_id:int,
     *      to_account_id:int,
     *      amount:int,
     *      instruction:string
     *   }>
     * }
     */
    public function execute(Ledger $ledger, string $referenceDate): array
    {
        $period = $this->settlementCycleService->resolvePeriodForDate($ledger, $referenceDate);

        return $this->executeForPeriod($ledger, $period);
    }

    /**
     * @return array{
     *   ledger_id:int,
     *   period_start:string,
     *   period_end:string,
     *   settlement_mode:string,
     *   summary:array{total_shared_spend:int, pool_current_balance:int},
     *   user_breakdowns:array<int, array{
     *      user_id:int,
     *      name:string,
     *      active_ratio:float,
     *      target_liability:int,
     *      paid_out_of_pocket:int,
     *      net_balance:int
     *   }>,
     *   required_transfers:array<int, array{
     *      from_account_id:int,
     *      to_account_id:int,
     *      amount:int,
     *      instruction:string
     *   }>
     * }
     */
    public function executeForPeriodEnd(Ledger $ledger, string $periodEnd): array
    {
        $period = $this->settlementCycleService->resolvePeriodForPeriodEnd($ledger, $periodEnd);

        return $this->executeForPeriod($ledger, $period);
    }

    /**
     * @param array{period_start:string, period_end:string} $period
     * @return array{
     *   ledger_id:int,
     *   period_start:string,
     *   period_end:string,
     *   settlement_mode:string,
     *   summary:array{total_shared_spend:int, pool_current_balance:int},
     *   user_breakdowns:array<int, array{
     *      user_id:int,
     *      name:string,
     *      active_ratio:float,
     *      target_liability:int,
     *      paid_out_of_pocket:int,
     *      net_balance:int
     *   }>,
     *   required_transfers:array<int, array{
     *      from_account_id:int,
     *      to_account_id:int,
     *      amount:int,
     *      instruction:string
     *   }>
     * }
     */
    private function executeForPeriod(Ledger $ledger, array $period): array
    {
        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        $users = $ledger->users()->select(['users.id', 'users.name'])->get();
        /** @var list<int> $userIds */
        $userIds = array_values(array_map('intval', $users->pluck('id')->all()));

        $personalAccountsByUser = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', AccountType::Personal->value)
            ->whereNotNull('owner_id')
            ->orderBy('id')
            ->get()
            ->groupBy('owner_id');

        $allAccounts = Account::query()
            ->where('ledger_id', $ledger->id)
            ->get()
            ->keyBy('id');

        /** @var array<int, int> $shareableByUser */
        $shareableByUser = $this->financialProfileService->shareableIncomeForUsers(
            $ledger,
            $userIds,
            $periodEnd,
        );

        $liabilityByUser = array_fill_keys($userIds, 0);
        $outOfPocketByUser = array_fill_keys($userIds, 0);

        $transactions = Transaction::query()
            ->with('creditAccount')
            ->where('ledger_id', $ledger->id)
            ->where('type', '!=', 'settlement')
            ->betweenDates($periodStart, $periodEnd)
            ->get();

        foreach ($transactions as $transaction) {
            $amount = (int) $transaction->amount;
            $splitRule = $transaction->split_rule instanceof BackedEnum
                ? $transaction->split_rule->value
                : (string) $transaction->split_rule;
            $participantsRaw = $transaction->participants;
            $participants = \is_array($participantsRaw) ? $participantsRaw : [];

            /** @var list<int> $participantUserIds */
            $participantUserIds = count($participants) > 0
                ? array_values(array_map(fn (array $p): int => (int) $p['user_id'], $participants))
                : $userIds;

            $allocations = $this->allocateByRule($splitRule, $amount, $participantUserIds, $participants, $shareableByUser);

            foreach ($allocations as $userId => $allocatedAmount) {
                if (array_key_exists($userId, $liabilityByUser)) {
                    $liabilityByUser[$userId] += $allocatedAmount;
                }
            }

            $creditAccount = $transaction->creditAccount;

            $creditAccountTypeValue = $creditAccount->type instanceof BackedEnum
                ? $creditAccount->type->value
                : (string) $creditAccount->type;

            if (
                $creditAccount !== null
                && $creditAccountTypeValue === AccountType::Personal->value
                && $creditAccount->owner_id !== null
                && array_key_exists($creditAccount->owner_id, $outOfPocketByUser)
            ) {
                $outOfPocketByUser[$creditAccount->owner_id] += $amount;
            }
        }

        $totalSharedSpend = (int) $transactions->sum('amount');
        $totalShareable = max(1, array_sum($shareableByUser));

        /** @var list<array{user_id:int, name:string, active_ratio:float, target_liability:int, paid_out_of_pocket:int, net_balance:int}> $userBreakdowns */
        $userBreakdowns = [];

        foreach ($users as $user) {
            $liability = $liabilityByUser[$user->id] ?? 0;
            $outOfPocket = $outOfPocketByUser[$user->id] ?? 0;
            $netBalance = $outOfPocket - $liability;
            $ratio = $shareableByUser[$user->id] / $totalShareable;

            $userBreakdowns[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'active_ratio' => round($ratio, 4),
                'target_liability' => $liability,
                'paid_out_of_pocket' => $outOfPocket,
                'net_balance' => $netBalance,
            ];
        }

        /** @var Collection<int, array{user_id:int, name:string, net_balance:int}> $userBreakdownsCollection */
        $userBreakdownsCollection = collect($userBreakdowns);
        /** @var Collection<int, Collection<int, Account>> $personalAccountsByUserCollection */
        $personalAccountsByUserCollection = $personalAccountsByUser;
        $requiredTransfers = $this->buildTransfers(
            $ledger,
            $userBreakdownsCollection,
            $personalAccountsByUserCollection,
            $allAccounts,
        );

        return [
            'ledger_id' => $ledger->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'settlement_mode' => $ledger->settlement_mode->value,
            'summary' => [
                'total_shared_spend' => $totalSharedSpend,
                'pool_current_balance' => 0,
            ],
            'user_breakdowns' => $userBreakdowns,
            'required_transfers' => $requiredTransfers,
        ];
    }

    /**
     * @param list<int> $participantUserIds
     * @param list<array{user_id: int, share?: int|float}> $participantsRaw
     * @param array<int, int> $shareableByUser
     * @return array<int, int> userId => allocated cents
     */
    private function allocateByRule(
        string $splitRule,
        int $amount,
        array $participantUserIds,
        array $participantsRaw,
        array $shareableByUser,
    ): array {
        if (count($participantUserIds) === 0) {
            return [];
        }

        return match ($splitRule) {
            'equal' => $this->allocateEqual($amount, $participantUserIds),
            'proportional' => $this->allocateProportional($amount, $participantUserIds, $shareableByUser),
            'individual' => [$participantUserIds[0] => $amount],
            'manual' => $this->allocateManual($amount, $participantsRaw),
            default => $this->allocateEqual($amount, $participantUserIds),
        };
    }

    /**
     * @param list<int> $participantUserIds
     * @return array<int, int>
     */
    private function allocateEqual(int $amount, array $participantUserIds): array
    {
        $count = count($participantUserIds);
        $base = intdiv($amount, $count);
        $remainder = $amount % $count;

        $result = [];

        foreach ($participantUserIds as $index => $userId) {
            $result[$userId] = $base + ($index < $remainder ? 1 : 0);
        }

        return $result;
    }

    /**
     * @param list<int> $participantUserIds
     * @param array<int, int> $shareableByUser
     * @return array<int, int>
     */
    private function allocateProportional(int $amount, array $participantUserIds, array $shareableByUser): array
    {
        $totalShareable = 0;

        foreach ($participantUserIds as $userId) {
            $totalShareable += $shareableByUser[$userId] ?? 0;
        }

        if ($totalShareable <= 0) {
            return $this->allocateEqual($amount, $participantUserIds);
        }

        $result = [];
        $runningTotal = 0;
        $lastIndex = count($participantUserIds) - 1;

        foreach ($participantUserIds as $index => $userId) {
            if ($index === $lastIndex) {
                $result[$userId] = $amount - $runningTotal;
            } else {
                $allocated = (int) floor(($shareableByUser[$userId] ?? 0) / $totalShareable * $amount);
                $result[$userId] = $allocated;
                $runningTotal += $allocated;
            }
        }

        return $result;
    }

    /**
     * @param list<array{user_id: int, share?: int|float}> $participantsRaw
     * @return array<int, int>
     */
    private function allocateManual(int $amount, array $participantsRaw): array
    {
        $totalShare = 0.0;

        foreach ($participantsRaw as $p) {
            $totalShare += (float) ($p['share'] ?? 0);
        }

        if ($totalShare <= 0) {
            return [];
        }

        $result = [];
        $runningTotal = 0;
        $lastIndex = count($participantsRaw) - 1;

        foreach ($participantsRaw as $index => $p) {
            $userId = (int) $p['user_id'];

            if ($index === $lastIndex) {
                $result[$userId] = $amount - $runningTotal;
            } else {
                $allocated = (int) floor(((float) ($p['share'] ?? 0)) / $totalShare * $amount);
                $result[$userId] = $allocated;
                $runningTotal += $allocated;
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, array{
     *   user_id:int,
     *   name:string,
     *   net_balance:int
     * }>  $userBreakdowns
     * @param Collection<int, Collection<int, Account>> $personalAccountsByUser
     * @param Collection<int, Account> $allAccounts
     * @return array<int, array{
     *   from_account_id:int,
     *   to_account_id:int,
     *   amount:int,
     *   instruction:string
     * }>
     */
    private function buildTransfers(
        Ledger $ledger,
        Collection $userBreakdowns,
        Collection $personalAccountsByUser,
        Collection $allAccounts,
    ): array {
        $debtors = $userBreakdowns
            ->filter(static fn (array $row): bool => $row['net_balance'] < 0)
            ->map(static fn (array $row): array => [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'amount' => abs($row['net_balance']),
            ])
            ->values();
        $creditors = $userBreakdowns
            ->filter(static fn (array $row): bool => $row['net_balance'] > 0)
            ->map(static fn (array $row): array => [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'amount' => $row['net_balance'],
            ])
            ->values();

        if ($ledger->settlement_mode === SettlementMode::JointClearinghouse) {
            $poolAccount = $allAccounts->first(
                static function (Account $account): bool {
                    $typeValue = $account->type instanceof BackedEnum
                        ? $account->type->value
                        : (string) $account->type;

                    return $typeValue === AccountType::Pool->value;
                },
            );

            if (!$poolAccount instanceof Account) {
                return [];
            }

            $transfers = $debtors
                ->map(function (array $debtor) use ($personalAccountsByUser, $poolAccount): ?array {
                    /** @var Account|null $fromAccount */
                    $fromAccount = $personalAccountsByUser
                        ->get($debtor['user_id'])
                        ?->first();

                    if (!$fromAccount instanceof Account || $debtor['amount'] === 0) {
                        return null;
                    }

                    return [
                        'from_account_id' => $fromAccount->id,
                        'to_account_id' => $poolAccount->id,
                        'amount' => $debtor['amount'],
                        'instruction' => "{$debtor['name']} needs to transfer {$debtor['amount']} cents to the Joint Pool",
                    ];
                })
                ->filter()
                ->values()
                ->all();

            foreach ($creditors as $creditor) {
                /** @var Account|null $toAccount */
                $toAccount = $personalAccountsByUser
                    ->get($creditor['user_id'])
                    ?->first();

                if ($toAccount instanceof Account && $creditor['amount'] > 0) {
                    $transfers[] = [
                        'from_account_id' => $poolAccount->id,
                        'to_account_id' => $toAccount->id,
                        'amount' => $creditor['amount'],
                        'instruction' => "Pool transfers {$creditor['amount']} cents to {$creditor['name']}",
                    ];
                }
            }

            return $transfers;
        }

        $transfers = [];
        $creditorIndex = 0;
        $remainingCreditors = $creditors->values()->all();

        foreach ($debtors as $debtor) {
            $remainingDebt = (int) $debtor['amount'];
            /** @var Account|null $fromAccount */
            $fromAccount = $personalAccountsByUser
                ->get($debtor['user_id'])
                ?->first();

            if (!$fromAccount instanceof Account) {
                continue;
            }

            while ($remainingDebt > 0 && $creditorIndex < count($remainingCreditors)) {
                $creditor = $remainingCreditors[$creditorIndex];
                $creditAmount = (int) $creditor['amount'];

                if ($creditAmount <= 0) {
                    $creditorIndex++;

                    continue;
                }

                $creditorUserId = $creditor['user_id'] ?? null;

                if (!is_int($creditorUserId)) {
                    $creditorIndex++;

                    continue;
                }

                /** @var Account|null $toAccount */
                $toAccount = $personalAccountsByUser
                    ->get($creditorUserId)
                    ?->first();

                if (!$toAccount instanceof Account) {
                    $creditorIndex++;

                    continue;
                }

                $transferAmount = min($remainingDebt, $creditAmount);
                $creditorName = $creditor['name'] ?? '';

                $transfers[] = [
                    'from_account_id' => $fromAccount->id,
                    'to_account_id' => $toAccount->id,
                    'amount' => $transferAmount,
                    'instruction' => "{$debtor['name']} transfers {$transferAmount} cents to {$creditorName}",
                ];

                $remainingDebt -= $transferAmount;
                $remainingCreditors[$creditorIndex]['amount'] -= $transferAmount;
            }
        }

        return $transfers;
    }
}
