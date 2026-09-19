<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Enums\TransactionType;
use App\Http\Resources\TransactionResource;
use App\Mcp\Support\PorukoTool;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Ledger\Actions\UpdateTransactionAction;
use App\Modules\Ledger\Data\PostManualTransactionData;
use App\Modules\Mcp\Services\ActorAccountAuthorizationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update a posted transaction the authenticated user is allowed to change. Payer must remain the actor\'s personal account or a pool account.')]
class UpdateTransactionTool extends PorukoTool
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
            'transaction_id' => $schema->integer()->required(),
            'payer_account_id' => $schema->integer()->required(),
            'destination_account_id' => $schema->integer()->required(),
            'amount' => $schema->integer()->required(),
            'description' => $schema->string(),
            'date' => $schema->string()->required(),
            'split_rule' => $schema->string()->enum(['equal', 'proportional', 'individual', 'manual'])->required(),
            'participants' => $schema->array(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $request->validate([
            'transaction_id' => ['required', 'integer'],
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

        $transaction = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->with('payerAccount')
            ->findOrFail((int) $validated['transaction_id']);

        if ($user->cannot('update', $transaction)) {
            abort(403, 'You cannot update this transaction.');
        }

        $payer = Account::query()->findOrFail((int) $validated['payer_account_id']);
        app(ActorAccountAuthorizationService::class)->assertMayUsePayerAccount($user, $payer);

        $updated = app(UpdateTransactionAction::class)->execute(
            $transaction,
            PostManualTransactionData::fromArray([
                ...$validated,
                'ledger_id' => $ledger->id,
                'type' => $transaction->type instanceof TransactionType
                    ? $transaction->type->value
                    : (string) $transaction->type,
                'source' => $transaction->source->value,
                'source_metadata' => $transaction->source_metadata,
            ]),
        );

        return [
            'data' => $this->resourceArray(TransactionResource::make(
                $updated->load(['payerAccount', 'destinationAccount', 'postings']),
            )),
        ];
    }
}
