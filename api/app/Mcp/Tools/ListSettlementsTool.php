<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Queries\SettlementIndexQuery;
use App\Modules\Ledger\Services\SettlementCycleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List settled periods and available settlement period windows for the space.')]
#[IsReadOnly]
class ListSettlementsTool extends PorukoTool
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
        return $this->ledgerIdSchema($schema);
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        return [
            'data' => $this->resourceCollection(app(SettlementIndexQuery::class)->execute($ledger)),
            'periods' => app(SettlementCycleService::class)->getAvailablePeriods($ledger),
        ];
    }
}
