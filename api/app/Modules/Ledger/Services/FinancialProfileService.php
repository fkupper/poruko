<?php

namespace App\Modules\Ledger\Services;

use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\User;
use Carbon\CarbonImmutable;

class FinancialProfileService
{
    /**
     * Resolve the active financial profile for a user within a ledger on a given date.
     */
    public function activeProfile(Ledger $ledger, User $user, string $date): ?FinancialProfile
    {
        return FinancialProfile::query()
            ->forLedgerUser($ledger->id, $user->id)
            ->activeOn($date)
            ->latest('valid_from')
            ->first();
    }

    /**
     * Upsert the active financial profile for a user.
     *
     * If no settlement has locked the current period, updates the existing
     * active profile in-place. Otherwise, closes the old profile and creates
     * a branched copy effective from today.
     *
     * @param array<int, array{description: string, amount: int}> $incomes
     * @param array<int, array{description: string, amount: int}> $deductions
     */
    public function upsertActive(Ledger $ledger, User $user, array $incomes, array $deductions): FinancialProfile
    {
        $now = CarbonImmutable::now();
        $today = $now->format('Y-m-d');

        $existing = $this->activeProfile($ledger, $user, $today);

        if ($existing === null) {
            return FinancialProfile::query()->create([
                'ledger_id' => $ledger->id,
                'user_id' => $user->id,
                'valid_from' => $now->startOfMonth()->format('Y-m-d'),
                'valid_to' => null,
                'incomes' => $incomes,
                'deductions' => $deductions,
            ]);
        }

        $latestSettlement = $ledger->settlements()
            ->whereNotNull('executed_at')
            ->latest('period_end')
            ->first();

        if ($latestSettlement === null) {
            $existing->update([
                'incomes' => $incomes,
                'deductions' => $deductions,
            ]);

            return $existing->refresh();
        }

        $periodEnd = $latestSettlement->period_end
            ? CarbonImmutable::parse($latestSettlement->period_end)->format('Y-m-d')
            : $today;

        if ($today > $periodEnd) {
            $existing->update([
                'incomes' => $incomes,
                'deductions' => $deductions,
            ]);

            return $existing->refresh();
        }

        $nextCycleDay = CarbonImmutable::parse($periodEnd)->addDay()->format('Y-m-d');

        $validFromStr = $existing->valid_from
            ? CarbonImmutable::parse($existing->valid_from)->format('Y-m-d')
            : null;

        if ($validFromStr === $nextCycleDay) {
            $existing->update([
                'incomes' => $incomes,
                'deductions' => $deductions,
            ]);

            return $existing->refresh();
        }

        $existing->update([
            'valid_to' => $periodEnd,
        ]);

        return FinancialProfile::query()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => $nextCycleDay,
            'valid_to' => null,
            'incomes' => $incomes,
            'deductions' => $deductions,
        ]);
    }

    /**
     * Compute the shareable income for a user in a ledger on a given date.
     * Returns 0 when no profile exists.
     */
    public function shareableIncomeOn(Ledger $ledger, User $user, string $date): int
    {
        $profile = $this->activeProfile($ledger, $user, $date);

        return $profile?->shareableIncome() ?? 0;
    }

    /**
     * Batch compute shareable income for multiple users in one query.
     *
     * @param list<int> $userIds
     * @return array<int, int> user_id => shareable_income
     */
    public function shareableIncomeForUsers(Ledger $ledger, array $userIds, string $date): array
    {
        if (count($userIds) === 0) {
            return [];
        }

        $profiles = FinancialProfile::query()
            ->where('ledger_id', $ledger->id)
            ->whereIn('user_id', $userIds)
            ->where('valid_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $date);
            })
            ->orderBy('user_id')
            ->orderByDesc('valid_from')
            ->get();

        $result = [];

        foreach ($profiles as $profile) {
            if (!array_key_exists($profile->user_id, $result)) {
                $result[$profile->user_id] = $profile->shareableIncome();
            }
        }

        foreach ($userIds as $id) {
            if (!array_key_exists($id, $result)) {
                $result[$id] = 0;
            }
        }

        return $result;
    }
}
