<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\AccountType;
use App\Enums\SettlementMode;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Modules\Ledger\Queries\LedgerMemberMainAccountsQuery;
use App\Modules\Ledger\Services\FinancialProfileService;
use App\Modules\Ledger\Services\SettlementCycleService;
use BackedEnum;
use Illuminate\Support\Collection;

readonly class PreviewSettlementAction
{
    public function __construct(
        private readonly FinancialProfileService $financialProfileService,
        private readonly SettlementCycleService $settlementCycleService,
        private readonly LedgerMemberMainAccountsQuery $ledgerMemberMainAccountsQuery,
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
    public function executeForPeriod(Ledger $ledger, array $period): array
    {
        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        $users = $ledger->allUsers()->select(['users.id', 'users.name'])->get();
        /** @var list<int> $userIds */
        $userIds = array_values(array_map('intval', $users->pluck('id')->all()));

        $allAccounts = Account::query()
            ->where('ledger_id', $ledger->id)
            ->get()
            ->keyBy('id');

        $mainAccountByUser = $this->ledgerMemberMainAccountsQuery->get($ledger, $allAccounts);

        /** @var array<int, int> $shareableByUser */
        $shareableByUser = $this->financialProfileService->shareableIncomeForUsers(
            $ledger,
            $userIds,
            $periodEnd,
        );

        $liabilityByUser = array_fill_keys($userIds, 0);
        $outOfPocketByUser = array_fill_keys($userIds, 0);

        // Fetch all postings up to periodEnd for all ledger accounts
        $postings = \App\Models\Posting::query()
            ->with('transaction')
            ->whereIn('account_id', $allAccounts->keys())
            ->whereHas('transaction', function ($q) use ($periodEnd) {
                $q->where('date', '<=', $periodEnd);
            })
            ->get();

        foreach ($postings as $posting) {
            $account = $allAccounts[$posting->account_id];
            $amount = (int) $posting->amount;

            $type = $account->type instanceof BackedEnum ? $account->type->value : (string) $account->type;

            $txDate = is_string($posting->transaction->date)
                ? mb_substr($posting->transaction->date, 0, 10)
                : $posting->transaction->date->toDateString();

            if ($txDate >= $periodStart) {
                if ($type === AccountType::UserLiability->value && $account->owner_id !== null) {
                    // Liability: Debits increase it, Credits decrease it
                    if ($posting->direction->value === \App\Enums\PostingDirection::Debit->value) {
                        $liabilityByUser[$account->owner_id] += $amount;
                    } else {
                        $liabilityByUser[$account->owner_id] -= $amount;
                    }
                } elseif ($type === AccountType::UserFunding->value && $account->owner_id !== null) {
                    // Funding (Equity): Credits increase it (user paid), Debits decrease it (user was reimbursed)
                    if ($posting->direction->value === \App\Enums\PostingDirection::Credit->value) {
                        $outOfPocketByUser[$account->owner_id] += $amount;
                    } else {
                        $outOfPocketByUser[$account->owner_id] -= $amount;
                    }
                }
            }
        }

        $totalSharedSpend = 0;
        $poolCurrentBalance = 0;

        foreach ($allAccounts as $account) {
            $type = $account->type instanceof BackedEnum ? $account->type->value : (string) $account->type;

            if ($type === AccountType::PoolAsset->value) {
                $poolCurrentBalance += (int) $account->base_budget;
            }
        }

        foreach ($postings as $posting) {
            $account = $allAccounts[$posting->account_id];
            $type = $account->type instanceof BackedEnum ? $account->type->value : (string) $account->type;

            if ($type === AccountType::PoolAsset->value) {
                if ($posting->direction->value === \App\Enums\PostingDirection::Debit->value) {
                    $poolCurrentBalance += (int) $posting->amount;
                } else {
                    $poolCurrentBalance -= (int) $posting->amount;
                }
            }

            if ($type === AccountType::SpaceExpense->value) {
                // Check if the transaction belongs to the current cycle
                $txDate = is_string($posting->transaction->date)
                    ? mb_substr($posting->transaction->date, 0, 10)
                    : $posting->transaction->date->toDateString();

                if ($txDate >= $periodStart) {
                    if ($posting->direction->value === \App\Enums\PostingDirection::Debit->value) {
                        $totalSharedSpend += (int) $posting->amount;
                    } else {
                        $totalSharedSpend -= (int) $posting->amount;
                    }
                }
            }
        }

        $totalShareable = max(1, array_sum($shareableByUser));

        /** @var list<array{user_id:int, name:string, active_ratio:float, target_liability:int, paid_out_of_pocket:int, net_balance:int}> $userBreakdowns */
        $userBreakdowns = [];

        foreach ($users as $user) {
            $liability = $liabilityByUser[$user->id] ?? 0;
            $outOfPocket = $outOfPocketByUser[$user->id] ?? 0;
            $netBalance = $outOfPocket - $liability; // Positive means they are owed, Negative means they owe
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
        $requiredTransfers = $this->buildTransfers(
            $ledger,
            $userBreakdownsCollection,
            $mainAccountByUser,
            $allAccounts,
        );

        $settlementModel = \App\Models\Settlement::query()
            ->where('ledger_id', $ledger->id)
            ->where('period_end', $periodEnd)
            ->first();

        return [
            'ledger_id' => $ledger->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_settled' => $settlementModel && $settlementModel->executed_at !== null,
            'executed_at' => $settlementModel?->executed_at?->toIso8601String(),
            'settlement_mode' => $ledger->settlement_mode->value,
            'summary' => [
                'total_shared_spend' => (int) $totalSharedSpend,
                'pool_current_balance' => (int) $poolCurrentBalance,
            ],
            'user_breakdowns' => $userBreakdowns,
            'required_transfers' => $requiredTransfers,
        ];
    }

    /**
     * @param  Collection<int, array{
     *   user_id:int,
     *   name:string,
     *   net_balance:int
     * }>  $userBreakdowns
     * @param array<int, Account> $mainAccountByUser
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
        array $mainAccountByUser,
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

        $getLiabilityAccount = fn ($userId) => $allAccounts->firstWhere(
            fn ($a) => ($a->type instanceof BackedEnum ? $a->type->value : (string) $a->type) === AccountType::UserLiability->value
            && $a->owner_id === $userId,
        );

        if ($ledger->settlement_mode === SettlementMode::JointClearinghouse) {
            $poolAccount = $allAccounts->first(
                static function (Account $account): bool {
                    $typeValue = $account->type instanceof BackedEnum
                        ? $account->type->value
                        : (string) $account->type;

                    return $typeValue === AccountType::PoolAsset->value;
                },
            );

            if (!$poolAccount instanceof Account) {
                return [];
            }

            $transfers = $debtors
                ->map(function (array $debtor) use ($getLiabilityAccount, $poolAccount): ?array {
                    $fromAccount = $getLiabilityAccount($debtor['user_id']);

                    if (!$fromAccount instanceof Account || $debtor['amount'] === 0) {
                        return null;
                    }

                    return [
                        'from_account_id' => $fromAccount->id,
                        'to_account_id' => $poolAccount->id,
                        'amount' => $debtor['amount'],
                        'instruction' => "{$debtor['name']} needs to transfer to the Joint Pool",
                    ];
                })
                ->filter()
                ->values()
                ->all();

            foreach ($creditors as $creditor) {
                $toAccount = $mainAccountByUser[$creditor['user_id']] ?? null;

                if ($toAccount instanceof Account && $creditor['amount'] > 0) {
                    $transfers[] = [
                        'from_account_id' => $poolAccount->id,
                        'to_account_id' => $toAccount->id,
                        'amount' => $creditor['amount'],
                        'instruction' => "Pool transfers to {$creditor['name']}",
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
            $fromAccount = $getLiabilityAccount($debtor['user_id']);

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

                // Creditors get paid into their Funding Account to reduce the amount the Space owes them
                $toAccount = $mainAccountByUser[$creditorUserId] ?? null;

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
                    'instruction' => "{$debtor['name']} transfers to {$creditorName}",
                ];

                $remainingDebt -= $transferAmount;
                $remainingCreditors[$creditorIndex]['amount'] -= $transferAmount;
            }
        }

        return $transfers;
    }
}
