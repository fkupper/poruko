<?php

namespace App\Modules\Ledger\Actions;

use App\Models\Account;

final readonly class DeleteAccountAction
{
    /**
     * @return array{deleted: bool, message?: string}
     */
    public function execute(Account $account): array
    {
        if ($account->postings()->exists()) {
            return [
                'deleted' => false,
                'message' => 'Account cannot be deleted once postings exist.',
            ];
        }

        $account->delete();

        return ['deleted' => true];
    }
}
