<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\AccountType;
use App\Enums\DeleteAccountResult;
use App\Models\Account;
use App\Modules\Ledger\Queries\PersonalAccountQuery;

final readonly class DeleteAccountAction
{
    public function __construct(
        private readonly PersonalAccountQuery $personalAccountQuery,
    ) {}

    public function execute(Account $account): DeleteAccountResult
    {
        if ($account->type === AccountType::Personal && $account->owner_id !== null) {
            if ($this->personalAccountQuery->isMain($account)) {
                return DeleteAccountResult::MainPersonalAccount;
            }

            if ($this->personalAccountQuery->countPersonalForOwnerInLedger(
                $account->ledger_id,
                $account->owner_id,
            ) === 1) {
                return DeleteAccountResult::LastPersonalAccount;
            }
        }

        if ($account->postings()->exists()) {
            return DeleteAccountResult::HasPostings;
        }

        $account->delete();

        return DeleteAccountResult::Deleted;
    }
}
