<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Http\Resources\RecurringTransactionResource;
use App\Mcp\Support\PorukoTool;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Modules\Ledger\Actions\UpdateRecurringTransactionAction;
use App\Modules\Ledger\Data\UpdateRecurringTransactionData;
use App\Modules\Mcp\Services\ActorAccountAuthorizationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update a recurring blueprint the authenticated user is allowed to change.')]
class UpdateRecurringBlueprintTool extends PorukoTool
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
            'blueprint_id' => $schema->integer()->required(),
            'payer_account_id' => $schema->integer(),
            'destination_account_id' => $schema->integer(),
            'amount' => $schema->integer(),
            'description' => $schema->string(),
            'split_rule' => $schema->string()->enum(['equal', 'proportional']),
            'start_date' => $schema->string(),
            'frequency' => $schema->string()->enum(['weekly', 'monthly', 'annual']),
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
            'blueprint_id' => ['required', 'integer'],
            'payer_account_id' => ['nullable', 'integer'],
            'destination_account_id' => ['nullable', 'integer'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'split_rule' => ['nullable', 'in:equal,proportional'],
            'start_date' => ['nullable', 'date'],
            'frequency' => ['nullable', 'in:weekly,monthly,annual'],
            'participants' => ['nullable', 'array'],
            'participants.*.user_id' => ['required', 'integer'],
        ]);

        $blueprint = RecurringTransaction::query()
            ->forLedger($ledger->id)
            ->with('payerAccount')
            ->findOrFail((int) $validated['blueprint_id']);

        if ($user->cannot('update', $blueprint)) {
            abort(403, 'You cannot update this recurring blueprint.');
        }

        if (isset($validated['payer_account_id'])) {
            $payer = Account::query()->findOrFail((int) $validated['payer_account_id']);
            app(ActorAccountAuthorizationService::class)->assertMayUsePayerAccount($user, $payer);
        }

        $updated = app(UpdateRecurringTransactionAction::class)->execute(
            $blueprint,
            UpdateRecurringTransactionData::fromArray($validated),
        );

        return [
            'data' => $this->resourceArray(RecurringTransactionResource::make(
                $updated->load(['payerAccount', 'destinationAccount']),
            )),
        ];
    }
}
