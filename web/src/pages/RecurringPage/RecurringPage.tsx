import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchRecurringBlueprints, createRecurringBlueprint } from '@/api/recurring';
import { fetchAccounts } from '@/api/accounts';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogClose, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PlusIcon, RepeatIcon } from 'lucide-react';
import type { SplitRule } from '@/api/types';

export default function RecurringPage() {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);

    const [isAddOpen, setIsAddOpen] = React.useState(false);
    const [description, setDescription] = React.useState('');
    const [amountCents, setAmountCents] = React.useState(0);
    const [frequency, setFrequency] = React.useState<'monthly' | 'weekly' | 'annual'>('monthly');
    const [splitRule, setSplitRule] = React.useState<SplitRule>('proportional');
    const [startDate, setStartDate] = React.useState(() => new Date().toISOString().split('T')[0]);
    const [payerAccountId, setPayerAccountId] = React.useState<number | null>(null);

    const { data: blueprints = [], isPending } = useQuery({
        queryKey: ['recurring', activeLedgerId],
        queryFn: () => fetchRecurringBlueprints(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const { data: accounts } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId && isAddOpen,
    });

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId) throw new Error('No active space.');
            return createRecurringBlueprint(activeLedgerId, {
                description,
                amount: amountCents,
                frequency,
                split_rule: splitRule,
                start_date: startDate,
                payer_account_id: payerAccountId || accounts?.[0]?.id || 1,
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['recurring', activeLedgerId] });
            setIsAddOpen(false);
            setDescription('');
            setAmountCents(0);
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        mutation.mutate();
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <RepeatIcon className="size-6 text-primary" />
                        Recurring Expense Blueprints
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Automate fixed monthly bills like rent, energy, and subscriptions with bi-temporal schedules.
                    </p>
                </div>
                <Button onClick={() => setIsAddOpen(true)} className="gap-2 shrink-0">
                    <PlusIcon className="size-4" />
                    New Blueprint
                </Button>
            </div>

            {isPending ? (
                <div className="h-48 rounded-xl border bg-muted/20 animate-pulse flex items-center justify-center text-sm text-muted-foreground">
                    Loading recurring blueprints...
                </div>
            ) : blueprints.length === 0 ? (
                <div className="rounded-xl border border-dashed p-12 text-center text-muted-foreground text-sm">
                    No recurring blueprints active. Add rent or utilities to auto-materialize every month.
                </div>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Blueprint Description</TableHead>
                            <TableHead>Frequency</TableHead>
                            <TableHead>Split Rule</TableHead>
                            <TableHead>Valid From</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {blueprints.map((bp) => (
                            <TableRow key={bp.id}>
                                <TableCell className="font-semibold">{bp.description}</TableCell>
                                <TableCell className="capitalize text-xs font-mono">{bp.frequency}</TableCell>
                                <TableCell>
                                    <Badge variant="outline" className="capitalize text-[11px]">
                                        {bp.split_rule}
                                    </Badge>
                                </TableCell>
                                <TableCell className="font-mono text-xs text-muted-foreground">{bp.valid_from}</TableCell>
                                <TableCell>
                                    <Badge variant={bp.is_active ? 'success' : 'secondary'}>
                                        {bp.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="text-right font-mono font-semibold text-foreground">
                                    {centsToCurrency(bp.amount)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}

            {/* Modal */}
            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogHeader>
                    <DialogTitle>New Recurring Expense Blueprint</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            Description
                        </label>
                        <input
                            type="text"
                            placeholder="e.g. Monthly Rent, Fiber Internet"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            required
                            className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">
                                Amount (€)
                            </label>
                            <CurrencyInput
                                value={amountCents}
                                onCentsChange={setAmountCents}
                                required
                            />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">
                                Start Date
                            </label>
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                required
                                className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 font-mono text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">
                                Frequency
                            </label>
                            <select
                                value={frequency}
                                onChange={(e) => setFrequency(e.target.value as 'monthly' | 'weekly' | 'annual')}
                                className="h-10 w-full rounded-lg border border-input bg-background px-3 py-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="monthly">Monthly</option>
                                <option value="weekly">Weekly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">
                                Split Rule
                            </label>
                            <select
                                value={splitRule}
                                onChange={(e) => setSplitRule(e.target.value as SplitRule)}
                                className="h-10 w-full rounded-lg border border-input bg-background px-3 py-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="proportional">Proportional</option>
                                <option value="equal">Equal</option>
                                <option value="individual">Individual</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">
                            Payer Account
                        </label>
                        {accounts && accounts.length > 0 ? (
                            <select
                                value={payerAccountId ?? ''}
                                onChange={(e) => setPayerAccountId(Number(e.target.value))}
                                className="h-10 w-full rounded-lg border border-input bg-background px-3 py-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                {accounts.map((acc) => (
                                    <option key={acc.id} value={acc.id}>
                                        {acc.name}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <input
                                type="number"
                                placeholder="Account ID (Default 1)"
                                value={payerAccountId ?? 1}
                                onChange={(e) => setPayerAccountId(Number(e.target.value))}
                                className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 text-sm"
                            />
                        )}
                    </div>

                    {mutation.isError && (
                        <p className="text-xs text-destructive">
                            {(mutation.error as Error)?.message || 'Failed to create blueprint.'}
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setIsAddOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={mutation.isPending || !description.trim() || amountCents <= 0}>
                            Save Blueprint
                        </Button>
                    </DialogFooter>
                </form>
                <DialogClose onClose={() => setIsAddOpen(false)} />
            </Dialog>
        </div>
    );
}
