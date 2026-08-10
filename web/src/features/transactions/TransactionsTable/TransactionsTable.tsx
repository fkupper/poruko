import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchTransactions, deleteTransaction, type FetchTransactionsFilters } from '@/api/transactions';
import { fetchAccounts } from '@/api/accounts';
import { fetchLedgerMembers } from '@/api/members';
import { fetchSettlementPeriods } from '@/api/settlements';
import type { Transaction } from '@/api/types';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { FacetedFilter } from '@/components/ui/faceted-filter';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { SearchIcon, MoreHorizontalIcon, PencilIcon, TrashIcon, LockIcon, ArrowRightIcon, XIcon, ReceiptIcon } from 'lucide-react';

interface TransactionsTableProps {
    fixedFilters?: FetchTransactionsFilters;
    showFilters?: boolean;
    initialAccountId?: number;
    onEditTransaction?: (tx: Transaction) => void;
}

const SPLIT_RULE_OPTIONS = [
    { label: 'Proportional', value: 'proportional' },
    { label: 'Equal', value: 'equal' },
    { label: 'Individual', value: 'individual' },
    { label: 'Manual', value: 'manual' },
];

const TYPE_OPTIONS = [
    { label: 'Manual', value: 'manual' },
    { label: 'Recurring', value: 'recurring' },
    { label: 'Settlement', value: 'settlement' },
    { label: 'Reversal', value: 'reversal' },
];

export function TransactionsTable({
    fixedFilters,
    showFilters = true,
    initialAccountId,
    onEditTransaction,
}: TransactionsTableProps) {
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();
    const queryClient = useQueryClient();

    const [searchQuery, setSearchQuery] = React.useState('');
    const [deletingId, setDeletingId] = React.useState<number | null>(null);

    // Selected Faceted Filters
    const [selectedCreators, setSelectedCreators] = React.useState<Set<string | number>>(new Set());
    const [selectedAccounts, setSelectedAccounts] = React.useState<Set<string | number>>(
        new Set(initialAccountId ? [initialAccountId] : [])
    );
    const [selectedSplitRules, setSelectedSplitRules] = React.useState<Set<string | number>>(new Set());
    const [selectedTypes, setSelectedTypes] = React.useState<Set<string | number>>(new Set());
    const [selectedCycles, setSelectedCycles] = React.useState<Set<string | number>>(new Set());

    // Auxiliary queries for filter dropdown options
    const { data: accounts = [] } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId && showFilters,
    });

    const { data: members = [] } = useQuery({
        queryKey: ['members', activeLedgerId],
        queryFn: () => fetchLedgerMembers(activeLedgerId!),
        enabled: !!activeLedgerId && showFilters,
    });

    const { data: periods = [] } = useQuery({
        queryKey: ['settlement-periods', activeLedgerId],
        queryFn: () => fetchSettlementPeriods(activeLedgerId!),
        enabled: !!activeLedgerId && showFilters,
    });

    // Build backend query filters
    const queryFilters = React.useMemo<FetchTransactionsFilters>(() => {
        const filters: FetchTransactionsFilters = {
            ...fixedFilters,
        };

        if (selectedCreators.size > 0) {
            filters.creator_user_ids = Array.from(selectedCreators).map(Number);
        }
        if (selectedAccounts.size > 0) {
            filters.account_ids = Array.from(selectedAccounts).map(Number);
        }
        if (selectedSplitRules.size > 0) {
            filters.split_rules = Array.from(selectedSplitRules).map(String);
        }
        if (selectedTypes.size > 0) {
            filters.types = Array.from(selectedTypes).map(String);
        }

        return filters;
    }, [fixedFilters, selectedCreators, selectedAccounts, selectedSplitRules, selectedTypes]);

    const { data: transactions = [], isPending } = useQuery({
        queryKey: ['transactions', activeLedgerId, queryFilters],
        queryFn: () => fetchTransactions(activeLedgerId!, queryFilters),
        enabled: !!activeLedgerId,
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: number) => {
            if (!activeLedgerId) return;
            return deleteTransaction(activeLedgerId, id);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['transactions', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['settlement-preview', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
        },
    });

    // Helper map for accounts & creators lookup
    const accountsMap = React.useMemo(() => {
        const map = new Map<number, (typeof accounts)[0]>();
        accounts.forEach((acc) => map.set(acc.id, acc));
        return map;
    }, [accounts]);

    const membersMap = React.useMemo(() => {
        const map = new Map<number, string>();
        members.forEach((m) => map.set(m.id, m.name));
        return map;
    }, [members]);

    // Local search filter (description)
    const filteredTransactions = React.useMemo(() => {
        let result = transactions;

        // Filter by cycles locally if period dates selected
        if (selectedCycles.size > 0) {
            const selectedPeriodLabels = Array.from(selectedCycles);
            const matchedPeriods = periods.filter((p) => selectedPeriodLabels.includes(p.label));
            result = result.filter((tx) => {
                return matchedPeriods.some((p) => tx.date >= p.period_start && tx.date <= p.period_end);
            });
        }

        if (!searchQuery.trim()) return result;
        const q = searchQuery.toLowerCase();
        return result.filter(
            (tx) =>
                tx.description?.toLowerCase().includes(q) ||
                tx.split_rule?.toLowerCase().includes(q) ||
                tx.date?.includes(q)
        );
    }, [transactions, searchQuery, selectedCycles, periods]);

    const hasActiveFilters =
        selectedCreators.size > 0 ||
        selectedAccounts.size > 0 ||
        selectedSplitRules.size > 0 ||
        selectedTypes.size > 0 ||
        selectedCycles.size > 0;

    const handleClearFilters = () => {
        setSelectedCreators(new Set());
        setSelectedAccounts(new Set());
        setSelectedSplitRules(new Set());
        setSelectedTypes(new Set());
        setSelectedCycles(new Set());
        setSearchQuery('');
    };

    const creatorOptions = React.useMemo(
        () => members.map((m) => ({ label: m.name, value: m.id })),
        [members]
    );

    const accountOptions = React.useMemo(
        () => accounts.map((acc) => ({ label: acc.name, value: acc.id })),
        [accounts]
    );

    const cycleOptions = React.useMemo(
        () => periods.map((p) => ({ label: p.label, value: p.label })),
        [periods]
    );

    const toggleFilterValue = (setter: React.Dispatch<React.SetStateAction<Set<string | number>>>, val: string | number) => {
        setter((prev) => {
            const next = new Set(prev);
            if (next.has(val)) {
                next.delete(val);
            } else {
                next.add(val);
            }
            return next;
        });
    };

    return (
        <div className="space-y-4">
            {showFilters && (
                <div className="space-y-3">
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <div className="relative flex-1">
                            <SearchIcon className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Filter transactions by description or date..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="h-9 pl-9"
                            />
                        </div>
                    </div>

                    {/* Faceted Filters Toolbar */}
                    <div className="flex flex-wrap items-center gap-2">
                        <FacetedFilter
                            title="Creator"
                            options={creatorOptions}
                            selectedValues={selectedCreators}
                            onSelect={(v) => toggleFilterValue(setSelectedCreators, v)}
                            onClear={() => setSelectedCreators(new Set())}
                        />

                        <FacetedFilter
                            title="Accounts"
                            options={accountOptions}
                            selectedValues={selectedAccounts}
                            onSelect={(v) => toggleFilterValue(setSelectedAccounts, v)}
                            onClear={() => setSelectedAccounts(new Set())}
                        />

                        <FacetedFilter
                            title="Cycle"
                            options={cycleOptions}
                            selectedValues={selectedCycles}
                            onSelect={(v) => toggleFilterValue(setSelectedCycles, v)}
                            onClear={() => setSelectedCycles(new Set())}
                        />

                        <FacetedFilter
                            title="Split Rule"
                            options={SPLIT_RULE_OPTIONS}
                            selectedValues={selectedSplitRules}
                            onSelect={(v) => toggleFilterValue(setSelectedSplitRules, v)}
                            onClear={() => setSelectedSplitRules(new Set())}
                        />

                        <FacetedFilter
                            title="Type"
                            options={TYPE_OPTIONS}
                            selectedValues={selectedTypes}
                            onSelect={(v) => toggleFilterValue(setSelectedTypes, v)}
                            onClear={() => setSelectedTypes(new Set())}
                        />

                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleClearFilters}
                                className="h-8 px-2 lg:px-3 text-xs gap-1 text-muted-foreground hover:text-foreground"
                            >
                                Reset
                                <XIcon className="size-3.5" />
                            </Button>
                        )}
                    </div>
                </div>
            )}

            {/* Transactions Data Table */}
            {isPending ? (
                <div className="flex flex-col gap-2 rounded-xl border p-4">
                    <Skeleton className="h-8 w-full" />
                    <Skeleton className="h-8 w-full" />
                    <Skeleton className="h-8 w-3/4" />
                </div>
            ) : filteredTransactions.length === 0 ? (
                <Empty className="border border-dashed">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <ReceiptIcon />
                        </EmptyMedia>
                        <EmptyTitle>No matching transactions found</EmptyTitle>
                        <EmptyDescription>Try adjusting filters or log a new expense.</EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-[70px]">ID</TableHead>
                            <TableHead className="w-[100px]">Date</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Creator</TableHead>
                            <TableHead>Accounts (From → To)</TableHead>
                            <TableHead>Split Rule</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                            <TableHead className="w-[50px]"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filteredTransactions.map((tx) => {
                            const payerAcc = tx.payer_account_id ? accountsMap.get(tx.payer_account_id) : undefined;
                            const destAcc = tx.destination_account_id ? accountsMap.get(tx.destination_account_id) : undefined;

                            const payerName = tx.payer_account_name || payerAcc?.name || '—';
                            const destName = tx.destination_account_name || destAcc?.name || '—';

                            const ownerId = tx.payer_account_owner_id ?? payerAcc?.owner_id;
                            const creatorName = ownerId ? membersMap.get(ownerId) || `User #${ownerId}` : 'Pool / System';

                            return (
                                <TableRow key={tx.id}>
                                    <TableCell className="font-mono text-xs text-muted-foreground">#{tx.id}</TableCell>
                                    <TableCell className="font-mono text-xs whitespace-nowrap">{tx.date}</TableCell>
                                    <TableCell className="font-medium">{tx.description}</TableCell>
                                    <TableCell className="text-xs font-medium text-muted-foreground">
                                        {creatorName}
                                    </TableCell>
                                    <TableCell className="text-xs">
                                        <div className="flex items-center gap-1 font-mono text-[11px] text-muted-foreground">
                                            <span className="font-medium text-foreground">{payerName}</span>
                                            <ArrowRightIcon className="size-3 shrink-0 opacity-50" />
                                            <span>{destName}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className="capitalize text-[11px]">
                                            {tx.split_rule}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="capitalize text-xs text-muted-foreground">
                                        {tx.type}
                                    </TableCell>
                                    <TableCell className="text-right font-mono font-semibold text-foreground">
                                        {centsToCurrency(tx.amount, currencySymbol)}
                                    </TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Actions for transaction ${tx.description}`}
                                                >
                                                    <MoreHorizontalIcon />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {tx.settlement_id !== null ? (
                                                    <div className="px-2 py-1.5 text-xs text-muted-foreground flex items-center">
                                                        <LockIcon className="size-3 mr-2" /> Locked (Settled)
                                                    </div>
                                                ) : (
                                                    <>
                                                        {onEditTransaction && (
                                                            <DropdownMenuItem onClick={() => onEditTransaction(tx)}>
                                                                <PencilIcon className="mr-2 size-4" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                        )}
                                                        <DropdownMenuItem
                                                            className="text-destructive focus:bg-destructive focus:text-destructive-foreground"
                                                            onClick={() => setDeletingId(tx.id)}
                                                        >
                                                            <TrashIcon className="mr-2 size-4" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            )}

            <AlertDialog open={!!deletingId} onOpenChange={(open) => !open && setDeletingId(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Transaction?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete this transaction.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={() => {
                                if (deletingId) {
                                    deleteMutation.mutate(deletingId);
                                    setDeletingId(null);
                                }
                            }}
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
