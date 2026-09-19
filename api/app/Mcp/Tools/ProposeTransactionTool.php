<?php

namespace App\Mcp\Tools;

use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;
use App\Modules\Mcp\Actions\SubmitMcpTransactionAction;
use App\Modules\Mcp\Services\McpCapabilityService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Always submit a transaction to the shared pending approval queue with source MCP. Does not post ledger balances until a human approves it.')]
class ProposeTransactionTool extends PostTransactionTool
{
    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $this->validatedTransactionPayload($request);
        $settings = app(McpCapabilityService::class)->membership($user, $ledger);
        assert($settings instanceof LedgerUser);

        $result = app(SubmitMcpTransactionAction::class)->execute(
            $user,
            $ledger,
            $settings,
            [...$validated, 'tool' => $this->name()],
            forceQueue: true,
        );

        return [
            'mode' => $result['mode'],
            'data' => $this->resourceArray(\App\Http\Resources\PendingTransactionResource::make($result['pending_transaction'])),
        ];
    }
}
