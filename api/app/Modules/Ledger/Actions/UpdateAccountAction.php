<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Account;
use App\Modules\Ledger\Data\UpdateAccountData;

final readonly class UpdateAccountAction
{
    public function execute(Account $account, UpdateAccountData $data): Account
    {
        $account->update($data->toArray());

        return $account->refresh();
    }
}
