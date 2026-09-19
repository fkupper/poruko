<?php

namespace App\Mcp\Support;

use App\Http\Resources\PendingTransactionResource;
use App\Http\Resources\TransactionResource;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use Illuminate\Support\Collection;

final class A2UiSurface
{
    /**
     * @param Collection<int, PendingTransaction> $pending
     * @return array<string, mixed>
     */
    public static function pendingApprovals(Collection $pending): array
    {
        $rows = PendingTransactionResource::collection($pending)->resolve();

        return [
            'a2ui' => [
                'version' => '0.8',
                'surfaceId' => 'poruko.pending-approvals',
                'catalogId' => 'poruko',
                'title' => 'Pending approvals',
                'description' => 'Shared approval queue. MCP action logs are a separate observability surface.',
                'components' => [
                    [
                        'id' => 'pending-table',
                        'type' => 'Table',
                        'columns' => ['id', 'source', 'description', 'amount', 'date', 'status'],
                        'rows' => $rows,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transactionInspection(Transaction $transaction): array
    {
        $data = TransactionResource::make($transaction)->resolve();

        return [
            'a2ui' => [
                'version' => '0.8',
                'surfaceId' => 'poruko.transaction-inspection',
                'catalogId' => 'poruko',
                'title' => 'Transaction',
                'description' => 'Posted transaction with explicit source provenance.',
                'components' => [
                    [
                        'id' => 'transaction-detail',
                        'type' => 'DefinitionList',
                        'items' => [
                            ['label' => 'Source', 'value' => $data['source'] ?? null],
                            ['label' => 'Type', 'value' => $data['type'] ?? null],
                            ['label' => 'Amount', 'value' => $data['amount'] ?? null],
                            ['label' => 'Description', 'value' => $data['description'] ?? null],
                            ['label' => 'Date', 'value' => $data['date'] ?? null],
                        ],
                    ],
                    [
                        'id' => 'source-metadata',
                        'type' => 'JsonBlock',
                        'value' => $data['source_metadata'] ?? null,
                    ],
                    [
                        'id' => 'postings',
                        'type' => 'Table',
                        'rows' => $data['postings'] ?? [],
                    ],
                ],
            ],
        ];
    }
}
