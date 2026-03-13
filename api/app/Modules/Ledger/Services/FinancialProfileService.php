<?php

namespace App\Modules\Ledger\Services;

use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\User;

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
     * @param  array<int, array{description: string, amount: int}>  $incomes
     * @param  array<int, array{description: string, amount: int}>  $deductions
     */
    public function upsertActive(Ledger $ledger, User $user, array $incomes, array $deductions): FinancialProfile
    {
        $now = now();
        $today = $now->format('Y-m-d');

        $existing = $this->activeProfile($ledger, $user, $today);

        if ($existing !== null) {
            $existing->update([
                'incomes' => $incomes,
                'deductions' => $deductions,
            ]);

            return $existing->refresh();
        }

        return FinancialProfile::query()->create([
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'valid_from' => $now->startOfMonth()->format('Y-m-d'),
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
}
