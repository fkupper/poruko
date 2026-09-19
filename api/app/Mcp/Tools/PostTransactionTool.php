<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Http\Resources\PendingTransactionResource;
use App\Http\Resources\TransactionResource;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use App\Modules\Mcp\Actions\SubmitMcpTransactionAction;
use App\Modules\Mcp\Services\McpCapabilityService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Submit an expense. Posts immediately when post_mode is direct, otherwise creates a pending approval with source MCP. Never spends from another member\'s personal account.')]
class PostTransactionTool extends PorukoTool
{
    protected function operation(): McpOperation
    {
        return McpOperation::Write;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->ledgerIdSchema($schema),
            'payer_account_id' => $schema->integer()->description('Payer account. Must be your user_funding account or a pool_asset.')->required(),
            'destination_account_id' => $schema->integer()->description('Space expense account.')->required(),
            'amount' => $schema->integer()->description('Amount in cents.')->required(),
            'description' => $schema->string()->description('Transaction description.'),
            'date' => $schema->string()->description('Transaction date (YYYY-MM-DD).')->required(),
            'split_rule' => $schema->string()->enum(['equal', 'proportional', 'individual', 'manual'])->required(),
            'participants' => $schema->array()->description('Split participants as {user_id, share?}.'),
        ];
    }

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
        );

        $pending = $result['pending_transaction'] ?? null;

        if ($pending instanceof PendingTransaction) {
            return [
                'mode' => $result['mode'],
                'data' => $this->resourceArray(PendingTransactionResource::make($pending)),
            ];
        }

        $transaction = $result['transaction'] ?? null;

        if ($transaction instanceof Transaction) {
            return [
                'mode' => $result['mode'],
                'data' => $this->resourceArray(TransactionResource::make($transaction)),
            ];
        }

        throw new InvalidLedgerPostingException(
            'The transaction could not be submitted.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedTransactionPayload(Request $request): array
    {
        return $request->validate([
            'payer_account_id' => ['required', 'integer'],
            'destination_account_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'split_rule' => ['required', 'in:equal,proportional,individual,manual'],
            'participants' => ['nullable', 'array'],
            'participants.*.user_id' => ['required', 'integer'],
            'participants.*.share' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
