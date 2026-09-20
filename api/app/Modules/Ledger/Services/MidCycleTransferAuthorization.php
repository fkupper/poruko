<?php

namespace App\Modules\Ledger\Services;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class MidCycleTransferAuthorization
{
    public function assertCanUseSource(User $user, Account $account): void
    {
        if ($user->cannot('useAsSource', $account)) {
            throw new AuthorizationException('You cannot record a mid-cycle transfer from another member\'s account.');
        }
    }

    /**
     * @param array<int, array{from_account_id:int, to_account_id:int, amount:int, instruction:string}> $transfers
     * @return array<int, array{from_account_id:int, to_account_id:int, from_account_owner_id:int|null, amount:int, instruction:string, can_record:bool}>
     */
    public function annotate(User $user, Ledger $ledger, array $transfers): array
    {
        $fromIds = array_values(array_unique(array_map(
            static fn (array $transfer): int => (int) $transfer['from_account_id'],
            $transfers,
        )));

        $accounts = $fromIds === []
            ? collect()
            : Account::query()->whereIn('id', $fromIds)->get()->keyBy('id');
        $canManageSettlements = $user->can('manageSettlements', $ledger);

        return array_map(function (array $transfer) use ($user, $accounts, $canManageSettlements): array {
            $fromAccount = $accounts->get((int) $transfer['from_account_id']);
            $transfer['from_account_owner_id'] = $fromAccount instanceof Account ? $fromAccount->owner_id : null;
            $transfer['can_record'] = $canManageSettlements
                && $fromAccount instanceof Account
                && $user->can('useAsSource', $fromAccount);

            return $transfer;
        }, $transfers);
    }
}
