import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchRecurringBlueprints, createRecurringBlueprint, updateRecurringBlueprint, deleteRecurringBlueprint } from '@/api/recurring';
import { fetchAccounts } from '@/api/accounts';
import { fetchLedgers } from '@/api/ledgers';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AccountSelector } from '@/components/ui/account-selector';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { PlusIcon, RepeatIcon, MoreHorizontalIcon, PencilIcon, TrashIcon, ChevronDownIcon, HistoryIcon } from 'lucide-react';
import type { SplitRule, RecurringBlueprint } from '@/api/types';

export default function RecurringPage() {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();

    const [filterStatus, setFilterStatus] = React.useState<'active' | 'deleted'>('active');
    const [openRows, setOpenRows] = React.useState<Record<number, boolean>>({});

    const [isAddOpen, setIsAddOpen] = React.useState(false);
    const [deletingId, setDeletingId] = React.useState<number | null>(null);
    const [editingId, setEditingId] = React.useState<number | null>(null);
    const [description, setDescription] = React.useState('');
    const [amountCents, setAmountCents] = React.useState<number | null>(null);
    const [frequency, setFrequency] = React.useState<'monthly' | 'weekly' | 'annual'>('monthly');
    const [splitRule, setSplitRule] = React.useState<SplitRule>('proportional');
    const [startDate, setStartDate] = React.useState(() => new Date().toISOString().split('T')[0]);
    const [payerAccountId, setPayerAccountId] = React.useState<number | null>(null);
    const [destinationAccountId, setDestinationAccountId] = React.useState<number | null>(null);

    const { data: blueprints = [], isPending } = useQuery({
        queryKey: ['recurring', activeLedgerId],
        queryFn: () => fetchRecurringBlueprints(activeLedgerId!, 'all'),
        enabled: !!activeLedgerId,
    });

    const { data: accounts } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId && isAddOpen,
    });

    const { data: ledgers } = useQuery({ queryKey: ['ledgers'], queryFn: fetchLedgers });

    const resolvedPayerAccountId =
        payerAccountId ?? (isAddOpen && accounts && accounts.length > 0 ? accounts[0].id : null);
    const resolvedDestinationAccountId =
        destinationAccountId ??
        (isAddOpen && accounts && accounts.length > 0
            ? (accounts.find((a) => a.type === 'space_expense')?.id ?? accounts[0].id)
            : null);

    const activeBlueprints = React.useMemo(
        () => blueprints.filter((bp) => bp.status === 'active'),
        [blueprints]
    );

    const previousVersions = React.useMemo(
        () => blueprints.filter((bp) => bp.status === 'previous_version'),
        [blueprints]
    );

    const deletedBlueprints = React.useMemo(
        () => blueprints.filter((bp) => bp.status === 'deleted'),
        [blueprints]
    );

    const getPreviousVersionsFor = (activeBp: RecurringBlueprint) => {
        return previousVersions
            .filter((pv) => pv.series_id === activeBp.series_id && pv.id !== activeBp.id)
            .sort((a, b) => new Date(b.valid_from).getTime() - new Date(a.valid_from).getTime());
    };

    const toggleRow = (id: number) => {
        setOpenRows((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const resetForm = () => {
        setDescription('');
        setAmountCents(null);
        setFrequency('monthly');
        setSplitRule('proportional');
        setStartDate(new Date().toISOString().split('T')[0]);
        setEditingId(null);
        
        const activeLedger = (ledgers || []).find(l => l.id === activeLedgerId);
        const prefs = activeLedger?.my_preferences;
        
        setPayerAccountId(prefs?.default_payment_account_id ?? prefs?.main_personal_account_id ?? null);
        setDestinationAccountId(prefs?.default_expense_account_id ?? null);
        
        setIsAddOpen(false);
    };

    React.useEffect(() => {
        if (isAddOpen && !editingId && accounts && accounts.length > 0) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setPayerAccountId(prev => prev ?? accounts[0].id);
             
            setDestinationAccountId(prev => prev ?? (accounts.find(a => a.type === 'space_expense')?.id ?? accounts[0].id));
        }
    }, [isAddOpen, editingId, accounts]);

    const handleEdit = (bp: RecurringBlueprint) => {
        setDescription(bp.description);
        setAmountCents(bp.amount);
        setFrequency(bp.frequency);
        setSplitRule(bp.split_rule);
        setStartDate(bp.valid_from);
        setPayerAccountId(bp.payer_account_id);
        setDestinationAccountId(bp.destination_account_id);
        setEditingId(bp.id);
        setIsAddOpen(true);
    };

    const saveMutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId) throw new Error('No active space.');
            if (!amountCents || amountCents <= 0) throw new Error('Please enter a valid amount.');
            if (!resolvedPayerAccountId) throw new Error('Please select a payer account.');
            if (!resolvedDestinationAccountId) throw new Error('Please select a destination account.');
            if (splitRule !== 'equal' && splitRule !== 'proportional') {
                throw new Error('Recurring bills only support Equal or Proportional splits.');
            }
            const payload = {
                description,
                amount: amountCents,
                frequency,
                split_rule: splitRule,
                start_date: startDate,
                payer_account_id: resolvedPayerAccountId,
                destination_account_id: resolvedDestinationAccountId,
            };
            if (editingId) {
                return updateRecurringBlueprint(activeLedgerId, editingId, payload);
            }
            return createRecurringBlueprint(activeLedgerId, payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['recurring', activeLedgerId] });
            resetForm();
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async (id: number) => {
            if (!activeLedgerId) throw new Error('No active space.');
            return deleteRecurringBlueprint(activeLedgerId, id);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['recurring', activeLedgerId] });
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        saveMutation.mutate();
    };

    const displayedBlueprints = filterStatus === 'active' ? activeBlueprints : deletedBlueprints;

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

            <div className="flex items-center justify-between">
                <ToggleGroup
                    type="single"
                    value={filterStatus}
                    onValueChange={(val) => {
                        if (val) setFilterStatus(val as 'active' | 'deleted');
                    }}
                    className="bg-muted/50 p-1 rounded-full border border-border"
                >
                    <ToggleGroupItem value="active" className="rounded-full px-4 text-xs font-medium data-[state=on]:bg-primary data-[state=on]:text-primary-foreground data-[state=on]:shadow-xs">
                        Active ({activeBlueprints.length})
                    </ToggleGroupItem>
                    <ToggleGroupItem value="deleted" className="rounded-full px-4 text-xs font-medium data-[state=on]:bg-primary data-[state=on]:text-primary-foreground data-[state=on]:shadow-xs">
                        Deleted ({deletedBlueprints.length})
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            {isPending ? (
                <div className="flex flex-col gap-2 rounded-xl border p-4">
                    <Skeleton className="h-8 w-full" />
                    <Skeleton className="h-8 w-full" />
                    <Skeleton className="h-8 w-3/4" />
                </div>
            ) : displayedBlueprints.length === 0 ? (
                <Empty className="border border-dashed">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <RepeatIcon />
                        </EmptyMedia>
                        <EmptyTitle>
                            {filterStatus === 'active'
                                ? 'No recurring blueprints active'
                                : 'No deleted recurring blueprints'}
                        </EmptyTitle>
                        <EmptyDescription>
                            {filterStatus === 'active'
                                ? 'Add rent or utilities to auto-materialize every month.'
                                : 'Deleted blueprints will appear here.'}
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-8"></TableHead>
                            <TableHead>Blueprint Description</TableHead>
                            <TableHead>Frequency</TableHead>
                            <TableHead>Split Rule</TableHead>
                            <TableHead>Valid From</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                            <TableHead className="w-10"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {displayedBlueprints.map((bp) => {
                            const pvs = filterStatus === 'active' ? getPreviousVersionsFor(bp) : [];
                            const hasPrevious = pvs.length > 0;
                            const isOpen = !!openRows[bp.id];

                            return (
                                <React.Fragment key={bp.id}>
                                    <TableRow className={filterStatus === 'deleted' ? 'opacity-70 bg-muted/20' : undefined}>
                                        <TableCell className="p-2 text-center">
                                            {hasPrevious && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={isOpen ? 'Collapse previous versions' : 'Expand previous versions'}
                                                    onClick={() => toggleRow(bp.id)}
                                                    title={`${pvs.length} previous version(s)`}
                                                >
                                                    <ChevronDownIcon
                                                        className={`transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                                                    />
                                                </Button>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-semibold flex items-center gap-2">
                                            {bp.description}
                                            {hasPrevious && (
                                                <Badge variant="outline" className="text-[10px] font-normal gap-1">
                                                    <HistoryIcon className="size-3" />
                                                    {pvs.length}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="capitalize text-xs font-mono">{bp.frequency}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className="capitalize text-[11px]">
                                                {bp.split_rule}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">{bp.valid_from}</TableCell>
                                        <TableCell>
                                            <Badge variant={bp.status === 'active' ? 'success' : 'destructive'}>
                                                {bp.status === 'active' ? 'Active' : 'Deleted'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right font-mono font-semibold text-foreground">
                                            {centsToCurrency(bp.amount, currencySymbol)}
                                        </TableCell>
                                        <TableCell>
                                            {bp.status === 'active' && (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Actions for ${bp.description}`}
                                                        >
                                                            <MoreHorizontalIcon />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem onClick={() => handleEdit(bp)}>
                                                            <PencilIcon className="mr-2 size-4" />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            className="text-destructive focus:bg-destructive focus:text-destructive-foreground"
                                                            onClick={() => setDeletingId(bp.id)}
                                                        >
                                                            <TrashIcon className="mr-2 size-4" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            )}
                                        </TableCell>
                                    </TableRow>

                                    {/* Nested Previous Versions */}
                                    {hasPrevious && isOpen && (
                                        <TableRow className="bg-muted/30 hover:bg-muted/40 transition-colors">
                                            <TableCell colSpan={8} className="p-0 border-b">
                                                <Collapsible open={isOpen}>
                                                    <CollapsibleContent className="p-4 pl-10 space-y-2">
                                                        <div className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                                            <HistoryIcon className="size-3.5" />
                                                            Previous Versions
                                                        </div>
                                                        <div className="divide-y divide-border/60 rounded-lg border border-border/60 bg-background/50 overflow-hidden">
                                                            {pvs.map((pv) => (
                                                                <div
                                                                    key={pv.id}
                                                                    className="flex items-center justify-between px-4 py-2.5 text-xs text-muted-foreground font-mono"
                                                                >
                                                                    <div className="flex items-center gap-4">
                                                                        <span>
                                                                            Valid: {pv.valid_from} → {pv.valid_to || 'Now'}
                                                                        </span>
                                                                        <span className="capitalize text-foreground/80">{pv.frequency}</span>
                                                                        <span className="capitalize">{pv.split_rule}</span>
                                                                    </div>
                                                                    <div className="font-semibold text-foreground">
                                                                        {centsToCurrency(pv.amount, currencySymbol)}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </CollapsibleContent>
                                                </Collapsible>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </React.Fragment>
                            );
                        })}
                    </TableBody>
                </Table>
            )}

            {/* Modal */}
            <Dialog
                open={isAddOpen}
                onOpenChange={(open) => {
                    setIsAddOpen(open);
                    if (!open) resetForm();
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingId ? 'Edit Recurring Expense Blueprint' : 'New Recurring Expense Blueprint'}</DialogTitle>
                    </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">Description</label>
                        <Input
                            type="text"
                            placeholder="e.g. Monthly Rent, Fiber Internet"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            required
                            className="h-10 w-full"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">Amount</label>
                            <CurrencyInput
                                value={amountCents}
                                onCentsChange={setAmountCents}
                                currencySymbol={currencySymbol}
                                required
                            />
                        </div>
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">Start Date</label>
                            <Input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                required
                                className="h-10 w-full font-mono text-sm"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">Frequency</label>
                            <Select value={frequency} onValueChange={(val) => setFrequency(val as 'monthly' | 'weekly' | 'annual')}>
                                <SelectTrigger className="h-10 w-full bg-background">
                                    <SelectValue placeholder="Select frequency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="monthly">Monthly</SelectItem>
                                        <SelectItem value="weekly">Weekly</SelectItem>
                                        <SelectItem value="annual">Annual</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">Split Rule</label>
                            <Select
                                value={
                                    splitRule === 'equal' || splitRule === 'proportional'
                                        ? splitRule
                                        : undefined
                                }
                                onValueChange={(val) => setSplitRule(val as SplitRule)}
                            >
                                <SelectTrigger className="h-10 w-full bg-background">
                                    <SelectValue
                                        placeholder={
                                            splitRule === 'individual' || splitRule === 'manual'
                                                ? `${splitRule} (choose Equal or Proportional)`
                                                : 'Select split rule'
                                        }
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="proportional">Proportional</SelectItem>
                                        <SelectItem value="equal">Equal</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            {splitRule === 'individual' || splitRule === 'manual' ? (
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    Individual and manual splits are not supported for recurring bills yet. Choose
                                    Equal or Proportional to save.
                                </p>
                            ) : null}
                        </div>
                    </div>

                    <AccountSelector
                        label="Payer Account"
                        usage="payer"
                        accounts={accounts}
                        value={resolvedPayerAccountId}
                        onChange={setPayerAccountId}
                    />

                    <AccountSelector
                        label="Category (Destination)"
                        usage="destination"
                        accounts={accounts}
                        value={resolvedDestinationAccountId}
                        onChange={setDestinationAccountId}
                    />

                    {saveMutation.isError && (
                        <p className="text-xs text-destructive">
                            {(saveMutation.error as Error)?.message || 'Failed to save blueprint.'}
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={resetForm}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                saveMutation.isPending ||
                                !description.trim() ||
                                (amountCents || 0) <= 0 ||
                                (splitRule !== 'equal' && splitRule !== 'proportional')
                            }
                        >
                            {editingId ? 'Save Changes' : 'Save Blueprint'}
                        </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <AlertDialog open={!!deletingId} onOpenChange={(open) => !open && setDeletingId(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will delete this blueprint. It will no longer execute in the future.
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
                            Delete Blueprint
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
