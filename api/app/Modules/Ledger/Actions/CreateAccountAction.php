<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Account;
use App\Models\Ledger;

final readonly class CreateAccountAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(Ledger $ledger, array $data): Account
    {
        return $ledger->accounts()->create($data);
    }
}
