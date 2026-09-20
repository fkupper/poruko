<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Http\Resources\FinancialProfileResource;
use App\Mcp\Support\PorukoTool;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Services\FinancialProfileService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update your own My Finance profile. You cannot update another member\'s profile.')]
class UpdateFinancialProfileTool extends PorukoTool
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
            'incomes' => $schema->array()->description('Income entries {description, amount cents}.')->required(),
            'deductions' => $schema->array()->description('Deduction entries {description, amount cents}.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        if ($user->cannot('update', [FinancialProfile::class, $ledger, $user])) {
            abort(403, 'You can only update your own financial profile.');
        }

        $validated = $request->validate([
            'incomes' => ['required', 'array', 'min:1'],
            'incomes.*.description' => ['required', 'string', 'max:255'],
            'incomes.*.amount' => ['required', 'integer', 'min:0'],
            'deductions' => ['present', 'array'],
            'deductions.*.description' => ['required', 'string', 'max:255'],
            'deductions.*.amount' => ['required', 'integer', 'min:0'],
        ]);

        $profile = app(FinancialProfileService::class)->upsertActive(
            $ledger,
            $user,
            $validated['incomes'],
            $validated['deductions'],
        );

        return [
            'data' => $this->resourceArray(FinancialProfileResource::make($profile)),
        ];
    }
}
