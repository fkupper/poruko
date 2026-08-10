<?php

namespace App\Modules\Ledger\Actions;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\LedgerUser;
use Illuminate\Support\Facades\DB;

final readonly class EnsureMainPersonalAccountForLedgerMemberAction
{
    public function execute(LedgerUser $ledgerUser): void
    {
        if ($ledgerUser->main_personal_account_id !== null) {
            return;
        }

        DB::transaction(function () use ($ledgerUser): void {
            $ledgerUser->refresh();

            if ($ledgerUser->main_personal_account_id !== null) {
                return;
            }

            $user = $ledgerUser->user;
            $ledger = $ledgerUser->ledger;

            $fundingAccount = Account::query()->create([
                'ledger_id' => $ledger->id,
                'owner_id' => $user->id,
                'type' => AccountType::UserFunding,
                'name' => "{$user->name}'s Funding Account",
                'base_budget' => 0,
            ]);

            Account::query()->create([
                'ledger_id' => $ledger->id,
                'owner_id' => $user->id,
                'type' => AccountType::UserLiability,
                'name' => "{$user->name}'s Liability Account",
                'base_budget' => 0,
            ]);

            $ledgerUser->main_personal_account_id = $fundingAccount->id;
            $ledgerUser->save();
        });
    }
}
