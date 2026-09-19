<?php

namespace App\Mcp\Tools;

use App\Enums\AccountType;
use App\Enums\McpOperation;
use App\Http\Resources\AccountResource;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\CreateAccountAction;
use App\Modules\Ledger\Data\CreateAccountData;
use App\Modules\Ledger\Queries\PersonalAccountQuery;
use App\Modules\Mcp\Services\ActorAccountAuthorizationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create an account in the space. Personal accounts can only be created for the authenticated user. Space CRUD is not available.')]
class CreateAccountTool extends PorukoTool
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
            'name' => $schema->string()->required(),
            'type' => $schema->string()->enum(['pool_asset', 'space_expense', 'user_funding'])->required(),
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:pool_asset,space_expense,user_funding'],
            'base_budget' => ['nullable', 'integer', 'min:0'],
        ]);

        $type = AccountType::from((string) $validated['type']);
        $data = CreateAccountData::fromArray([
            'name' => $validated['name'],
            'type' => $type,
            'owner_id' => $type === AccountType::UserFunding ? $user->id : null,
            'base_budget' => $validated['base_budget'] ?? null,
        ]);

        app(ActorAccountAuthorizationService::class)->assertMayCreateAccount($user, $data);
        $account = app(CreateAccountAction::class)->execute($ledger, $data);
        $account->setAttribute('is_main', app(PersonalAccountQuery::class)->isMain($account));

        return [
            'data' => $this->resourceArray(AccountResource::make($account)),
        ];
    }
}
