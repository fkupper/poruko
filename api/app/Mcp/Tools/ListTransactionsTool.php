<?php

namespace App\Mcp\Tools;

use App\Enums\McpOperation;
use App\Mcp\Support\PorukoTool;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Data\TransactionIndexFiltersData;
use App\Modules\Ledger\Queries\LedgerTransactionIndexQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List posted transactions for a space with optional filters. Pending proposals are not included.')]
#[IsReadOnly]
class ListTransactionsTool extends PorukoTool
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
            'from_date' => $schema->string()->description('Inclusive start date (YYYY-MM-DD).'),
            'to_date' => $schema->string()->description('Inclusive end date (YYYY-MM-DD).'),
            'page' => $schema->integer()->description('Page number.')->default(1),
            'per_page' => $schema->integer()->description('Results per page.')->default(15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function run(Request $request, User $user, ?Ledger $ledger): array
    {
        assert($ledger instanceof Ledger);

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = app(LedgerTransactionIndexQuery::class)->execute(
            $ledger,
            TransactionIndexFiltersData::fromArray($validated),
        );

        return [
            'data' => $this->resourceCollection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }
}
