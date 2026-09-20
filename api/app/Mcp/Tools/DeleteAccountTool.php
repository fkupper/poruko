<?php

namespace App\Mcp\Tools;

use App\Enums\DeleteAccountResult;
use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Actions\DeleteAccountAction;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use App\Modules\Mcp\Services\ActorAccountAuthorizationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Delete an account you are allowed to delete. You cannot delete another member\'s personal account.')]
#[IsDestructive]
class DeleteAccountTool extends PorukoTool
{
    protected function operation(): McpOperation
    {
        return McpOperation::Destructive;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->ledgerIdSchema($schema),
            'account_id' => $schema->integer()->required(),
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
        ]);

        $account = Account::query()
            ->where('ledger_id', $ledger->id)
            ->findOrFail((int) $validated['account_id']);

        if ($user->cannot('delete', $account)) {
            abort(403, 'You cannot delete this account.');
        }

        app(ActorAccountAuthorizationService::class)->assertMayMutateAccount($user, $account);

        $result = app(DeleteAccountAction::class)->execute($account);

        return match ($result) {
            DeleteAccountResult::Deleted => [
                'deleted' => true,
                'id' => (int) $validated['account_id'],
            ],
            DeleteAccountResult::MainPersonalAccount => throw new InvalidLedgerPostingException(
                'Cannot delete your main personal account.',
            ),
            DeleteAccountResult::LastPersonalAccount => throw new InvalidLedgerPostingException(
                'Cannot delete your last personal account.',
            ),
            DeleteAccountResult::AttachedToProcess => throw new InvalidLedgerPostingException(
                'Account cannot be deleted because it is attached to an active process.',
            ),
        };
    }
}
