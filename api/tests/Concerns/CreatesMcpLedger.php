<?php

namespace Tests\Concerns;

use App\Enums\AccountType;
use App\Enums\McpPostMode;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;

trait CreatesMcpLedger
{
    /**
     * @param array{enabled?:bool, read?:bool, write?:bool, destructive?:bool, post_mode?:string} $mcp
     * @return array{0: Ledger, 1: User, 2: Account, 3: Account, 4: User, 5: Account}
     */
    protected function createMcpLedger(array $mcp = []): array
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();
        $peer = User::factory()->create();
        $ledger->users()->attach($user->id, ['role' => 'admin']);
        $ledger->users()->attach($peer->id, ['role' => 'member']);
        setPermissionsTeamId($ledger->id);
        $user->assignRole('Admin');
        $peer->assignRole('Member');

        $payerAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $user->id,
            'type' => AccountType::UserFunding,
        ]);
        $peerPayerAccount = Account::factory()->create([
            'ledger_id' => $ledger->id,
            'owner_id' => $peer->id,
            'type' => AccountType::UserFunding,
        ]);
        $destinationAccount = Account::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', AccountType::SpaceExpense->value)
            ->firstOrFail();

        LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->update([
                'mcp_enabled' => $mcp['enabled'] ?? true,
                'mcp_allow_read' => $mcp['read'] ?? true,
                'mcp_allow_write' => $mcp['write'] ?? true,
                'mcp_allow_destructive' => $mcp['destructive'] ?? true,
                'mcp_post_mode' => $mcp['post_mode'] ?? McpPostMode::ApprovalQueue->value,
            ]);

        return [$ledger, $user, $payerAccount, $destinationAccount, $peer, $peerPayerAccount];
    }
}
