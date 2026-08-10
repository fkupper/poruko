<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Account;
use App\Models\Ledger;
use App\Modules\Ledger\Data\CreateAccountData;

final readonly class CreateAccountAction
{
    public function execute(Ledger $ledger, CreateAccountData $data): Account
    {
        return $ledger->accounts()->create($data->toArray());
    }
}
