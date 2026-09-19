import { useQuery } from '@tanstack/react-query';

import { fetchTransaction } from '@/api/transactions';
import type { TransactionSource } from '@/api/types';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerStore } from '@/stores/ledgerStore';

interface TransactionDetailSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transactionId: number | null;
}

const SOURCE_LABELS: Record<TransactionSource, string> = {
    manual: 'Manual',
    blueprint: 'Blueprint',
    mcp: 'MCP',
    ai_import: 'AI import',
    system: 'System',
};

function formatMetadataValue(value: unknown): string {
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return JSON.stringify(value) ?? String(value);
}

export function TransactionDetailSheet({
    open,
    onOpenChange,
    transactionId,
}: TransactionDetailSheetProps) {
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();
    const { data: transaction, isPending, isError } = useQuery({
        queryKey: ['transaction', activeLedgerId, transactionId],
        queryFn: () => fetchTransaction(activeLedgerId!, transactionId!),
        enabled: open && activeLedgerId !== null && transactionId !== null,
    });

    const metadataEntries = Object.entries(transaction?.source_metadata ?? {});

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {transaction ? `Transaction #${transaction.id}` : 'Transaction details'}
                    </SheetTitle>
                    <SheetDescription>
                        Accounting details, origin, and generated postings.
                    </SheetDescription>
                </SheetHeader>

                {isPending ? (
                    <div className="flex flex-col gap-3 px-4">
                        <Skeleton className="h-8 w-32" />
                        <Skeleton className="h-24 w-full" />
                        <Skeleton className="h-32 w-full" />
                    </div>
                ) : isError || !transaction ? (
                    <p className="px-4 text-sm text-destructive">Unable to load this transaction.</p>
                ) : (
                    <div className="flex flex-col gap-5 px-4 pb-6">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">{SOURCE_LABELS[transaction.source]}</Badge>
                            <Badge variant="outline" className="capitalize">
                                {transaction.type}
                            </Badge>
                        </div>

                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                            <dt className="text-muted-foreground">Description</dt>
                            <dd className="text-right font-medium">{transaction.description || '—'}</dd>
                            <dt className="text-muted-foreground">Date</dt>
                            <dd className="text-right font-mono">{transaction.date}</dd>
                            <dt className="text-muted-foreground">Amount</dt>
                            <dd className="text-right font-mono font-semibold">
                                {centsToCurrency(transaction.amount, currencySymbol)}
                            </dd>
                            <dt className="text-muted-foreground">From</dt>
                            <dd className="text-right">{transaction.payer_account_name || '—'}</dd>
                            <dt className="text-muted-foreground">To</dt>
                            <dd className="text-right">{transaction.destination_account_name || '—'}</dd>
                            <dt className="text-muted-foreground">Split rule</dt>
                            <dd className="text-right capitalize">{transaction.split_rule}</dd>
                        </dl>

                        {metadataEntries.length > 0 && (
                            <>
                                <Separator />
                                <section className="flex flex-col gap-2">
                                    <h3 className="text-sm font-semibold">Source context</h3>
                                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                                        {metadataEntries.map(([key, value]) => (
                                            <div className="contents" key={key}>
                                                <dt className="text-muted-foreground">
                                                    {key.replaceAll('_', ' ')}
                                                </dt>
                                                <dd className="break-all text-right font-mono text-xs">
                                                    {formatMetadataValue(value)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </section>
                            </>
                        )}

                        <Separator />
                        <section className="flex flex-col gap-2">
                            <h3 className="text-sm font-semibold">Postings</h3>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Account</TableHead>
                                        <TableHead>Direction</TableHead>
                                        <TableHead className="text-right">Amount</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {(transaction.postings ?? []).map((posting) => (
                                        <TableRow key={posting.id}>
                                            <TableCell className="font-mono">#{posting.account_id}</TableCell>
                                            <TableCell className="capitalize">{posting.direction}</TableCell>
                                            <TableCell className="text-right font-mono">
                                                {centsToCurrency(posting.amount, currencySymbol)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </section>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
