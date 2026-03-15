<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Account;

final readonly class UpdateAccountAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(Account $account, array $data): Account
    {
        $account->update($data);

        return $account->refresh();
    }
}
