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

            $account = Account::query()->create([
                'ledger_id' => $ledger->id,
                'owner_id' => $user->id,
                'type' => AccountType::Personal,
                'name' => "{$user->name}'s Personal Account",
                'base_budget' => 0,
            ]);

            $ledgerUser->main_personal_account_id = $account->id;
            $ledgerUser->save();
        });
    }
}
