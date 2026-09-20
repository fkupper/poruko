<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Http\Resources\FinancialProfileResource;
use App\Mcp\Support\PorukoTool;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Services\FinancialProfileService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get a My Finance profile. Defaults to the authenticated user. You may view another member in the same space but cannot manage users.')]
#[IsReadOnly]
class GetFinancialProfileTool extends PorukoTool
{
    protected function operation(): McpOperation
    {
        return McpOperation::Read;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->ledgerIdSchema($schema),
            'user_id' => $schema->integer()->description('Target member id. Defaults to the authenticated user.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
        ]);

        $target = isset($validated['user_id'])
            ? User::query()->findOrFail((int) $validated['user_id'])
            : $user;

        if ($user->cannot('view', [FinancialProfile::class, $ledger, $target])) {
            abort(403, 'You cannot view this financial profile.');
        }

        $profile = app(FinancialProfileService::class)->activeProfile(
            $ledger,
            $target,
            Carbon::now()->format('Y-m-d'),
        );

        if ($profile === null) {
            return [
                'data' => null,
                'message' => 'No active financial profile found.',
            ];
        }

        return [
            'data' => $this->resourceArray(FinancialProfileResource::make($profile)),
        ];
    }
}
