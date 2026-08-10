<?php

namespace App\Modules\Ledger\Queries;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\LedgerUser;

class PersonalAccountQuery
{
    public function isMain(Account $account): bool
    {
        if ($account->owner_id === null) {
            return false;
        }

        return LedgerUser::query()
            ->where('ledger_id', $account->ledger_id)
            ->where('user_id', $account->owner_id)
            ->where('main_personal_account_id', $account->id)
            ->exists();
    }

    public function countPersonalForOwnerInLedger(int $ledgerId, int $ownerId): int
    {
        return Account::query()
            ->where('ledger_id', $ledgerId)
            ->where('owner_id', $ownerId)
            ->where('type', AccountType::UserFunding)
            ->count();
    }
}
