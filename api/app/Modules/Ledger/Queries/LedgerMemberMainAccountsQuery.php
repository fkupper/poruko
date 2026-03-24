<?php

namespace App\Modules\Ledger\Queries;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\LedgerUser;
use Illuminate\Support\Collection;

class LedgerMemberMainAccountsQuery
{
    /**
     * @param Collection<int, Account> $allAccounts keyed by account ID
     * @return array<int, Account> user_id => main Account
     */
    public function get(Ledger $ledger, Collection $allAccounts): array
    {
        $mainAccountIdByUser = LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->whereNotNull('main_personal_account_id')
            ->pluck('main_personal_account_id', 'user_id');

        /** @var array<int, Account> $mainAccountByUser */
        $mainAccountByUser = [];

        foreach ($mainAccountIdByUser as $userId => $accountId) {
            $account = $allAccounts->get((int) $accountId);

            if ($account instanceof Account) {
                $mainAccountByUser[(int) $userId] = $account;
            }
        }

        return $mainAccountByUser;
    }
}
