<?php

namespace App\Modules\Mcp\Services;

use App\Enums\McpOperation;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;
use App\Modules\Mcp\Exceptions\McpAuthorizationException;

final class McpCapabilityService
{
    public function membership(User $user, Ledger $ledger): LedgerUser
    {
        $membership = LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership instanceof LedgerUser) {
            throw new McpAuthorizationException('You are not a member of this space.');
        }

        return $membership;
    }

    public function assert(
        User $user,
        ?Ledger $ledger,
        McpOperation $operation,
        bool $requiresEnabled = true,
    ): ?LedgerUser {
        if ($ledger === null) {
            return null;
        }

        $membership = $this->membership($user, $ledger);

        if ($requiresEnabled && $membership->mcp_enabled !== true) {
            throw new McpAuthorizationException(
                'MCP access is disabled for this space. Enable it in Space Settings.',
            );
        }

        $allowed = match ($operation) {
            McpOperation::Read => $membership->mcp_allow_read === true,
            McpOperation::Write => $membership->mcp_allow_write === true,
            McpOperation::Destructive => $membership->mcp_allow_destructive === true,
        };

        if (!$allowed) {
            throw new McpAuthorizationException(
                "MCP {$operation->value} operations are disabled for this space.",
            );
        }

        return $membership;
    }
}
