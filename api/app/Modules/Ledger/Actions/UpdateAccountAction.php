<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\AccountType;
use App\Models\Account;
use App\Modules\Ledger\Data\UpdateAccountData;

final readonly class UpdateAccountAction
{
    public function execute(Account $account, UpdateAccountData $data): Account
    {
        $updatePayload = $data->toArray();

        if ($data->currentFunds !== null) {
            $credits = (int) $account->getAttribute('total_credits');
            $debits = (int) $account->getAttribute('total_debits');

            $currentBalance = $account->base_budget;

            if ($credits > 0 || $debits > 0) {
                $currentBalance += match ($account->type) {
                    AccountType::SplitClearing,
                    AccountType::UserFunding => $credits - $debits,
                    default => $debits - $credits,
                };
            }

            $difference = $data->currentFunds - $currentBalance;
            $updatePayload['base_budget'] = $account->base_budget + $difference;
        }

        $account->update($updatePayload);

        return $account->refresh();
    }
}
