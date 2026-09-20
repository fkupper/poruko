<?php

namespace App\Mcp\Support;

use App\Http\Resources\PendingTransactionResource;
use App\Http\Resources\TransactionResource;
use App\Models\PendingTransaction;
use App\Models\Transaction;
use BackedEnum;
use Illuminate\Support\Collection;

final class A2UiSurface
{
    /**
     * @param  Collection<int, PendingTransaction>  $pending
     * @return array<string, mixed>
     */
    public static function pendingApprovals(Collection $pending): array
    {
        $rows = PendingTransactionResource::collection($pending)->resolve();
        $children = ['pending-title', 'pending-description'];
        $components = [
            [
                'id' => 'pending-title',
                'component' => 'Text',
                'text' => 'Pending approvals',
                'variant' => 'h2',
            ],
            [
                'id' => 'pending-description',
                'component' => 'Text',
                'text' => 'Shared approval queue. MCP action logs are a separate observability surface.',
                'variant' => 'caption',
            ],
        ];

        foreach (array_slice($rows, 0, 10) as $index => $row) {
            $cardId = "pending-card-{$index}";
            $contentId = "pending-content-{$index}";
            $summaryId = "pending-summary-{$index}";
            $metaId = "pending-meta-{$index}";
            $children[] = $cardId;
            $components[] = [
                'id' => $cardId,
                'component' => 'Card',
                'child' => $contentId,
            ];
            $components[] = [
                'id' => $contentId,
                'component' => 'Column',
                'children' => [$summaryId, $metaId],
            ];
            $components[] = [
                'id' => $summaryId,
                'component' => 'Text',
                'text' => sprintf(
                    '%s — %s',
                    (string) ($row['suggested_description'] ?? 'Pending transaction'),
                    self::minorUnits((int) ($row['suggested_amount'] ?? 0)),
                ),
                'variant' => 'h4',
            ];
            $components[] = [
                'id' => $metaId,
                'component' => 'Text',
                'text' => sprintf(
                    'Source: %s · Date: %s · Status: %s',
                    self::scalar($row['source'] ?? 'unknown'),
                    (string) ($row['date'] ?? 'unknown'),
                    self::scalar($row['status'] ?? 'pending'),
                ),
                'variant' => 'caption',
            ];
        }

        if (count($rows) === 0) {
            $children[] = 'pending-empty';
            $components[] = [
                'id' => 'pending-empty',
                'component' => 'Text',
                'text' => 'There are no pending transactions to review.',
            ];
        }

        array_unshift($components, [
            'id' => 'root',
            'component' => 'Column',
            'children' => $children,
        ]);

        return self::surface('poruko.pending-approvals', $components);
    }

    /**
     * @return array<string, mixed>
     */
    public static function transactionInspection(Transaction $transaction): array
    {
        $data = TransactionResource::make($transaction)->resolve();
        $components = [
            [
                'id' => 'root',
                'component' => 'Column',
                'children' => ['transaction-title', 'transaction-source', 'transaction-details', 'posting-title', 'posting-list'],
            ],
            [
                'id' => 'transaction-title',
                'component' => 'Text',
                'text' => (string) ($data['description'] ?? 'Transaction'),
                'variant' => 'h2',
            ],
            [
                'id' => 'transaction-source',
                'component' => 'Text',
                'text' => sprintf(
                    'Source: %s · Type: %s',
                    self::scalar($data['source'] ?? 'unknown'),
                    self::scalar($data['type'] ?? 'unknown'),
                ),
                'variant' => 'caption',
            ],
            [
                'id' => 'transaction-details',
                'component' => 'Card',
                'child' => 'transaction-detail-content',
            ],
            [
                'id' => 'transaction-detail-content',
                'component' => 'Column',
                'children' => ['transaction-amount', 'transaction-date'],
            ],
            [
                'id' => 'transaction-amount',
                'component' => 'Text',
                'text' => self::minorUnits((int) ($data['amount'] ?? 0)),
                'variant' => 'h3',
            ],
            [
                'id' => 'transaction-date',
                'component' => 'Text',
                'text' => 'Date: '.(string) ($data['date'] ?? 'unknown'),
                'variant' => 'caption',
            ],
            [
                'id' => 'posting-title',
                'component' => 'Text',
                'text' => 'Postings',
                'variant' => 'h3',
            ],
        ];
        $postingChildren = [];

        foreach (($data['postings'] ?? []) as $index => $posting) {
            $postingId = "posting-{$index}";
            $postingChildren[] = $postingId;
            $components[] = [
                'id' => $postingId,
                'component' => 'Text',
                'text' => sprintf(
                    '%s %s · account #%s',
                    ucfirst(self::scalar($posting['direction'] ?? 'posting')),
                    self::minorUnits((int) ($posting['amount'] ?? 0)),
                    (string) ($posting['account_id'] ?? 'unknown'),
                ),
            ];
        }

        $components[] = [
            'id' => 'posting-list',
            'component' => 'List',
            'children' => $postingChildren,
            'direction' => 'vertical',
        ];

        return self::surface('poruko.transaction-inspection', $components);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    private static function surface(string $surfaceId, array $components): array
    {
        return [
            'protocol' => 'a2ui',
            'version' => 'v0.9',
            'surface_id' => $surfaceId,
            'messages' => [
                [
                    'version' => 'v0.9',
                    'createSurface' => [
                        'surfaceId' => $surfaceId,
                        'catalogId' => 'https://a2ui.org/specification/v0_9/catalogs/basic/catalog.json',
                    ],
                ],
                [
                    'version' => 'v0.9',
                    'updateComponents' => [
                        'surfaceId' => $surfaceId,
                        'components' => $components,
                    ],
                ],
            ],
        ];
    }

    private static function minorUnits(int $amount): string
    {
        return number_format($amount / 100, 2);
    }

    private static function scalar(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
