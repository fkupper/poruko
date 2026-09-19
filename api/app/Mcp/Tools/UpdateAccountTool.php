<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Http\Resources\AccountResource;
use App\Mcp\Support\PorukoTool;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\UpdateAccountAction;
use App\Modules\Ledger\Data\UpdateAccountData;
use App\Modules\Ledger\Queries\PersonalAccountQuery;
use App\Modules\Mcp\Services\ActorAccountAuthorizationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update an account. You cannot rename, reassign, or edit another member\'s personal account.')]
class UpdateAccountTool extends PorukoTool
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
            'account_id' => $schema->integer()->required(),
            'name' => $schema->string(),
            'base_budget' => $schema->integer(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'base_budget' => ['nullable', 'integer', 'min:0'],
        ]);

        $account = Account::query()
            ->where('ledger_id', $ledger->id)
            ->findOrFail((int) $validated['account_id']);

        if ($user->cannot('update', $account)) {
            abort(403, 'You cannot update this account.');
        }

        app(ActorAccountAuthorizationService::class)->assertMayMutateAccount($user, $account);

        $updated = app(UpdateAccountAction::class)->execute(
            $account,
            UpdateAccountData::fromArray(array_filter([
                'name' => $validated['name'] ?? null,
                'base_budget' => $validated['base_budget'] ?? null,
            ], fn ($value) => $value !== null)),
        );
        $updated->setAttribute('is_main', app(PersonalAccountQuery::class)->isMain($updated));

        return [
            'data' => $this->resourceArray(AccountResource::make($updated)),
        ];
    }
}
