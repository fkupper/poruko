<?php

namespace Tests\Feature\Api;

use App\Enums\TransactionSource;
use App\Models\PendingTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreatesMcpLedger;
use Tests\TestCase;

#[Group('mcp')]
class PendingTransactionProposeApiTest extends TestCase
{
    use CreatesMcpLedger;
    use RefreshDatabase;

    public function testMemberCanProposePendingTransactionWithMcpSource(): void
    {
        [$ledger, $user, $payer, $destination] = $this->createMcpLedger();
        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/pending-transactions", [
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'amount' => 1200,
            'description' => 'WebMCP coffee',
            'date' => '2026-09-19',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.source', TransactionSource::Mcp->value)
            ->assertJsonPath('data.suggested_amount', 1200);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('pending_transactions', 1);
    }

    public function testCannotProposeUsingAnotherMembersPayer(): void
    {
        [$ledger, $user, , $destination, , $peerPayer] = $this->createMcpLedger();
        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/pending-transactions", [
            'payer_account_id' => $peerPayer->id,
            'destination_account_id' => $destination->id,
            'amount' => 1200,
            'description' => 'No',
            'date' => '2026-09-19',
            'split_rule' => 'equal',
            'participants' => [['user_id' => $user->id]],
        ])->assertForbidden();

        $this->assertSame(0, PendingTransaction::query()->count());
    }

    public function testNonMemberCannotPropose(): void
    {
        [$ledger, , $payer, $destination] = $this->createMcpLedger();
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->postJson("/api/ledgers/{$ledger->id}/pending-transactions", [
            'payer_account_id' => $payer->id,
            'destination_account_id' => $destination->id,
            'amount' => 1200,
            'description' => 'No',
            'date' => '2026-09-19',
            'split_rule' => 'equal',
        ])->assertForbidden();
    }
}
