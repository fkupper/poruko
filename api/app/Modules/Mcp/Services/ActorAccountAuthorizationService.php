<?php

namespace App\Modules\Mcp\Services;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use App\Modules\Ledger\Data\CreateAccountData;
use App\Modules\Mcp\Exceptions\McpAuthorizationException;

final class ActorAccountAuthorizationService
{
    public function assertMayUsePayerAccount(User $actor, Account $payer): void
    {
        if ($payer->type === AccountType::PoolAsset) {
            return;
        }

        if ($payer->type === AccountType::UserFunding && $payer->owner_id === $actor->id) {
            return;
        }

        throw new McpAuthorizationException(
            'You can only spend from your own personal account or a shared pool account.',
        );
    }

    public function assertMayMutateAccount(User $actor, Account $account): void
    {
        if ($account->owner_id === null) {
            return;
        }

        if ($account->owner_id !== $actor->id) {
            throw new McpAuthorizationException(
                'You cannot change another member\'s personal account.',
            );
        }
    }

    public function assertMayCreateAccount(User $actor, CreateAccountData $data): void
    {
        if (
            in_array($data->type, [AccountType::UserFunding, AccountType::UserLiability], true)
            && $data->ownerId !== null
            && $data->ownerId !== $actor->id
        ) {
            throw new McpAuthorizationException(
                'You cannot create a personal account for another member.',
            );
        }
    }
}
