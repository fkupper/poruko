<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Http\Resources\RecurringTransactionResource;
use App\Mcp\Support\PorukoTool;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Modules\Ledger\Actions\CreateRecurringTransactionAction;
use App\Modules\Ledger\Data\CreateRecurringTransactionData;
use App\Modules\Mcp\Services\ActorAccountAuthorizationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a recurring blueprint. The payer must be your personal account or a shared pool account.')]
class CreateRecurringBlueprintTool extends PorukoTool
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
            'payer_account_id' => $schema->integer()->required(),
            'destination_account_id' => $schema->integer()->required(),
            'amount' => $schema->integer()->required(),
            'description' => $schema->string(),
            'split_rule' => $schema->string()->enum(['equal', 'proportional'])->required(),
            'start_date' => $schema->string()->required(),
            'frequency' => $schema->string()->enum(['weekly', 'monthly', 'annual'])->default('monthly'),
            'participants' => $schema->array(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        if ($user->cannot('create', [RecurringTransaction::class, $ledger])) {
            abort(403, 'You cannot create recurring blueprints.');
        }

        $validated = $request->validate([
            'payer_account_id' => ['required', 'integer'],
            'destination_account_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'split_rule' => ['required', 'in:equal,proportional'],
            'start_date' => ['required', 'date'],
            'frequency' => ['nullable', 'in:weekly,monthly,annual'],
            'participants' => ['nullable', 'array'],
            'participants.*.user_id' => ['required', 'integer'],
        ]);

        $payer = Account::query()->findOrFail((int) $validated['payer_account_id']);
        app(ActorAccountAuthorizationService::class)->assertMayUsePayerAccount($user, $payer);

        $blueprint = app(CreateRecurringTransactionAction::class)->execute(
            $ledger,
            CreateRecurringTransactionData::fromArray($validated),
        );

        return [
            'data' => $this->resourceArray(RecurringTransactionResource::make(
                $blueprint->load(['payerAccount', 'destinationAccount']),
            )),
        ];
    }
}
