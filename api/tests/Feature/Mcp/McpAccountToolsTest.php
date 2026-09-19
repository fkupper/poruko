<?php

namespace Tests\Feature\Mcp;

use App\Enums\AccountType;
use App\Mcp\Servers\PorukoLedgerServer;
use App\Mcp\Tools\CreateAccountTool;
use App\Mcp\Tools\DeleteAccountTool;
use App\Mcp\Tools\ListAccountsTool;
use App\Mcp\Tools\UpdateAccountTool;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
class McpAccountToolsTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testCannotCreatePersonalAccountForAnotherMember(): void
    {
        [$ledger, $user, , , $peer] = $this->createMcpLedger();

        PorukoLedgerServer::actingAs($user)->tool(CreateAccountTool::class, [
            'ledger_id' => $ledger->id,
            'name' => 'Peer wallet',
            'type' => AccountType::UserFunding->value,
        ])->assertOk();

        $this->assertDatabaseHas('accounts', [
            'ledger_id' => $ledger->id,
            'name' => 'Peer wallet',
            'owner_id' => $user->id,
            'type' => AccountType::UserFunding->value,
        ]);
        $this->assertDatabaseMissing('accounts', [
            'ledger_id' => $ledger->id,
            'owner_id' => $peer->id,
            'name' => 'Peer wallet',
        ]);
    }

    public function testCannotUpdateOrDeleteAnotherMembersPersonalAccount(): void
    {
        [$ledger, $user, , , , $peerPayer] = $this->createMcpLedger();

        PorukoLedgerServer::actingAs($user)->tool(UpdateAccountTool::class, [
            'ledger_id' => $ledger->id,
            'account_id' => $peerPayer->id,
            'name' => 'Hijacked',
        ])->assertHasErrors(['You cannot change another member\'s personal account.']);

        PorukoLedgerServer::actingAs($user)->tool(DeleteAccountTool::class, [
            'ledger_id' => $ledger->id,
            'account_id' => $peerPayer->id,
        ])->assertHasErrors(['You cannot change another member\'s personal account.']);

        $this->assertDatabaseHas('accounts', [
            'id' => $peerPayer->id,
            'name' => $peerPayer->name,
        ]);
    }

    public function testMemberCanListAccountsWhenReadEnabled(): void
    {
        [$ledger, $user, $payer] = $this->createMcpLedger();

        PorukoLedgerServer::actingAs($user)->tool(ListAccountsTool::class, [
            'ledger_id' => $ledger->id,
        ])->assertOk()->assertSee($payer->name);

        $this->assertTrue(Account::query()->where('ledger_id', $ledger->id)->exists());
    }
}
