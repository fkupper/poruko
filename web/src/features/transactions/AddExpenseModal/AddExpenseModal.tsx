import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { fetchLedgerMembers } from '@/api/members';
import { fetchAccounts } from '@/api/accounts';
import { fetchLedgers } from '@/api/ledgers';
import { createTransaction, updateTransaction } from '@/api/transactions';
import type { ParticipantShare, SplitRule, Transaction } from '@/api/types';
import { centsToCurrency, formatPercent } from '@/lib/currency';
import {
    allocateEqualCents,
    allocateManualCents,
    allocateProportionalCents,
    isIndividualValid,
    isManualValid,
} from '@/lib/split';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { ReceiptIcon } from 'lucide-react';
import { AccountSelector } from '@/components/ui/account-selector';

interface AddExpenseModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction?: Transaction | null;
}

const SPLIT_RULE_OPTIONS: { value: SplitRule; label: string; hint: string }[] = [
    { value: 'proportional', label: 'Proportional', hint: 'By shareable income' },
    { value: 'equal', label: 'Equal', hint: 'Split evenly' },
    { value: 'individual', label: 'Individual', hint: "One person's cost" },
    { value: 'manual', label: 'Manual', hint: 'Custom ratios (weights)' },
];

function seedManualDefaults(memberIds: number[]): {
    selected: Set<number>;
    weights: Record<number, number>;
} {
    return {
        selected: new Set(memberIds),
        weights: Object.fromEntries(memberIds.map((id) => [id, 1])),
    };
}

export function AddExpenseModal({ open, onOpenChange, transaction }: AddExpenseModalProps) {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();

    const [description, setDescription] = React.useState('');
    const [amountCents, setAmountCents] = React.useState<number | null>(null);
    const [date, setDate] = React.useState(() => new Date().toISOString().split('T')[0]);
    const [payerAccountId, setPayerAccountId] = React.useState<number | null>(null);
    const [destinationAccountId, setDestinationAccountId] = React.useState<number | null>(null);
    const [splitRule, setSplitRule] = React.useState<SplitRule>('proportional');
    const [individualUserId, setIndividualUserId] = React.useState<number | null>(null);
    const [manualSelectedIds, setManualSelectedIds] = React.useState<Set<number>>(() => new Set());
    const [manualWeights, setManualWeights] = React.useState<Record<number, number>>({});
    const manualInitializedRef = React.useRef(false);

    const { data: ledgers } = useQuery({ queryKey: ['ledgers'], queryFn: fetchLedgers });

    React.useEffect(() => {
        if (open) {
            if (transaction) {
                // eslint-disable-next-line react-hooks/set-state-in-effect
                setDescription(transaction.description || '');
                setAmountCents(transaction.amount);
                setDate(transaction.date);
                setPayerAccountId(transaction.payer_account_id ?? null);
                setDestinationAccountId(transaction.destination_account_id ?? null);
                setSplitRule(transaction.split_rule);

                const participants = transaction.participants ?? [];
                if (transaction.split_rule === 'individual') {
                    setIndividualUserId(participants[0]?.user_id ?? null);
                    setManualSelectedIds(new Set());
                    setManualWeights({});
                    manualInitializedRef.current = false;
                } else if (transaction.split_rule === 'manual') {
                    setIndividualUserId(null);
                    setManualSelectedIds(new Set(participants.map((p) => p.user_id)));
                    const weights: Record<number, number> = {};
                    participants.forEach((p) => {
                        weights[p.user_id] = p.share && p.share > 0 ? p.share : 1;
                    });
                    setManualWeights(weights);
                    manualInitializedRef.current = true;
                } else {
                    setIndividualUserId(null);
                    setManualSelectedIds(new Set());
                    setManualWeights({});
                    manualInitializedRef.current = false;
                }
            } else {
                setDescription('');
                setAmountCents(null);
                setDate(new Date().toISOString().split('T')[0]);

                const activeLedger = (ledgers || []).find((l) => l.id === activeLedgerId);
                const prefs = activeLedger?.my_preferences;

                setPayerAccountId(prefs?.default_payment_account_id ?? prefs?.main_personal_account_id ?? null);
                setDestinationAccountId(prefs?.default_expense_account_id ?? null);
                setSplitRule('proportional');
                setIndividualUserId(null);
                setManualSelectedIds(new Set());
                setManualWeights({});
                manualInitializedRef.current = false;
            }
        }
    }, [open, transaction, ledgers, activeLedgerId]);

    const { data: accounts } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId && open,
    });

    React.useEffect(() => {
        if (accounts && accounts.length > 0) {
            if (payerAccountId === null) {
                // eslint-disable-next-line react-hooks/set-state-in-effect -- seed defaults once accounts load
                setPayerAccountId(accounts[0].id);
            }
            if (destinationAccountId === null) {
                const spaceExpense = accounts.find((a) => a.type === 'space_expense');
                setDestinationAccountId(spaceExpense?.id ?? accounts[0].id);
            }
        }
    }, [accounts, payerAccountId, destinationAccountId]);

    const { data: members = [], isPending: isLoadingMembers } = useQuery({
        queryKey: ['members', activeLedgerId, date],
        queryFn: () => fetchLedgerMembers(activeLedgerId!, date),
        enabled: !!activeLedgerId && open,
    });

    const activeMembers = React.useMemo(
        () => members.filter((m) => m.is_active !== false),
        [members],
    );

    React.useEffect(() => {
        if (!open || activeMembers.length === 0) {
            return;
        }

        if (splitRule === 'individual' && individualUserId === null) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- default to first member when needed
            setIndividualUserId(activeMembers[0].id);
        }

        // Seed manual once per open/rule switch after members load — do not re-seed if the user clears all.
        if (splitRule === 'manual' && !manualInitializedRef.current) {
            manualInitializedRef.current = true;
            const defaults = seedManualDefaults(activeMembers.map((m) => m.id));
            setManualSelectedIds(defaults.selected);
            setManualWeights(defaults.weights);
        }
    }, [open, activeMembers, splitRule, individualUserId]);

    const totalShareableIncome = React.useMemo(
        () => activeMembers.reduce((sum, m) => sum + (m.shareable_income || 0), 0),
        [activeMembers],
    );

    const manualParticipants = React.useMemo(
        () =>
            activeMembers
                .filter((m) => manualSelectedIds.has(m.id))
                .map((m) => ({
                    user_id: m.id,
                    share: manualWeights[m.id] ?? 0,
                })),
        [activeMembers, manualSelectedIds, manualWeights],
    );

    const previewAllocations = React.useMemo<Record<number, number>>(() => {
        const amt = amountCents || 0;
        if (!activeMembers.length || amt <= 0) {
            return {};
        }

        const participantIds = activeMembers.map((m) => m.id);

        if (splitRule === 'equal') {
            return allocateEqualCents(amt, participantIds);
        }

        if (splitRule === 'proportional') {
            const shareableByUser: Record<number, number> = {};
            activeMembers.forEach((m) => {
                shareableByUser[m.id] = m.shareable_income || 0;
            });
            return allocateProportionalCents(amt, participantIds, shareableByUser);
        }

        if (splitRule === 'individual' && isIndividualValid(individualUserId)) {
            return { [individualUserId!]: amt };
        }

        if (splitRule === 'manual' && isManualValid(manualParticipants)) {
            return allocateManualCents(amt, manualParticipants);
        }

        return {};
    }, [activeMembers, amountCents, splitRule, individualUserId, manualParticipants]);

    const payloadParticipants = React.useMemo<ParticipantShare[]>(() => {
        const amt = amountCents || 0;

        if (splitRule === 'individual') {
            if (!isIndividualValid(individualUserId)) {
                return [];
            }
            return [{ user_id: individualUserId! }];
        }

        if (splitRule === 'manual') {
            return manualParticipants;
        }

        if (!activeMembers.length || amt <= 0) {
            return [];
        }

        if (splitRule === 'equal') {
            const shares = allocateEqualCents(
                amt,
                activeMembers.map((m) => m.id),
            );
            const equalRatio = 1 / activeMembers.length;
            return activeMembers.map((m) => ({
                user_id: m.id,
                share: shares[m.id] ?? 0,
                share_ratio: equalRatio,
            }));
        }

        const shareableByUser: Record<number, number> = {};
        activeMembers.forEach((m) => {
            shareableByUser[m.id] = m.shareable_income || 0;
        });
        const shares = allocateProportionalCents(
            amt,
            activeMembers.map((m) => m.id),
            shareableByUser,
        );
        return activeMembers.map((m) => ({
            user_id: m.id,
            share: shares[m.id] ?? 0,
            share_ratio:
                totalShareableIncome > 0
                    ? (m.shareable_income || 0) / totalShareableIncome
                    : 1 / activeMembers.length,
        }));
    }, [
        activeMembers,
        amountCents,
        splitRule,
        individualUserId,
        manualParticipants,
        totalShareableIncome,
    ]);

    const splitValid =
        splitRule === 'equal' ||
        splitRule === 'proportional' ||
        (splitRule === 'individual' && isIndividualValid(individualUserId)) ||
        (splitRule === 'manual' && isManualValid(manualParticipants));

    const splitHelper = SPLIT_RULE_OPTIONS.find((o) => o.value === splitRule)?.hint ?? '';

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId) throw new Error('No active space selected.');
            if (!amountCents || amountCents <= 0) throw new Error('Please enter a valid amount.');
            if (!splitValid) {
                if (splitRule === 'individual') {
                    throw new Error('Select exactly one person for an individual expense.');
                }
                throw new Error('Select at least one participant and enter a weight greater than zero for each.');
            }
            const selectedPayerId = payerAccountId || accounts?.find((a) => a.type !== 'space_expense')?.id;
            const selectedDestinationId =
                destinationAccountId || accounts?.find((a) => a.type === 'space_expense')?.id;
            if (!selectedPayerId) {
                throw new Error('Please select a payer account.');
            }
            if (!selectedDestinationId) {
                throw new Error('Please select a destination account.');
            }

            const payload = {
                description: description.trim() || 'Expense',
                amount: amountCents,
                date,
                payer_account_id: selectedPayerId,
                destination_account_id: selectedDestinationId,
                type: 'manual' as const,
                split_rule: splitRule,
                participants: payloadParticipants,
            };

            if (transaction) {
                return updateTransaction(activeLedgerId, transaction.id, payload);
            }
            return createTransaction(activeLedgerId, payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['transactions', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['settlement-preview', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
            onOpenChange(false);
            setDescription('');
            setAmountCents(null);
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        mutation.mutate();
    };

    const handleSplitRuleChange = (value: string) => {
        if (!value) return;
        const next = value as SplitRule;
        setSplitRule(next);

        if (next === 'manual') {
            manualInitializedRef.current = true;
            if (activeMembers.length > 0) {
                const defaults = seedManualDefaults(activeMembers.map((m) => m.id));
                setManualSelectedIds(defaults.selected);
                setManualWeights((prev) => {
                    const nextWeights = { ...defaults.weights };
                    activeMembers.forEach((m) => {
                        if (prev[m.id] && prev[m.id] > 0) {
                            nextWeights[m.id] = prev[m.id];
                        }
                    });
                    return nextWeights;
                });
            } else {
                manualInitializedRef.current = false;
            }
        } else {
            manualInitializedRef.current = false;
        }

        if (next === 'individual' && activeMembers.length > 0 && individualUserId === null) {
            setIndividualUserId(activeMembers[0].id);
        }
    };

    const toggleManualMember = (memberId: number, checked: boolean) => {
        setManualSelectedIds((prev) => {
            const next = new Set(prev);
            if (checked) {
                next.add(memberId);
            } else {
                next.delete(memberId);
            }
            return next;
        });
        if (checked) {
            setManualWeights((prev) => ({
                ...prev,
                [memberId]: prev[memberId] && prev[memberId] > 0 ? prev[memberId] : 1,
            }));
        }
    };

    const showRoundingNote =
        (splitRule === 'equal' || splitRule === 'proportional' || splitRule === 'manual') &&
        (splitRule === 'manual' ? manualParticipants.length > 1 : activeMembers.length > 1);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <ReceiptIcon className="size-5 text-primary" />
                        {transaction ? 'Edit Expense' : 'Log New Expense'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="expense-description">Description</FieldLabel>
                            <Input
                                id="expense-description"
                                type="text"
                                placeholder="e.g. Weekly Groceries, Pizza"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                required
                            />
                        </Field>

                        <div className="grid grid-cols-2 gap-3">
                            <Field>
                                <FieldLabel htmlFor="expense-amount">Amount</FieldLabel>
                                <CurrencyInput
                                    id="expense-amount"
                                    value={amountCents}
                                    onCentsChange={setAmountCents}
                                    currencySymbol={currencySymbol}
                                    required
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="expense-date">Date</FieldLabel>
                                <Input
                                    id="expense-date"
                                    type="date"
                                    value={date}
                                    onChange={(e) => setDate(e.target.value)}
                                    required
                                    className="font-mono"
                                />
                            </Field>
                        </div>

                        <AccountSelector
                            label="Paid From Account"
                            usage="payer"
                            accounts={accounts}
                            value={payerAccountId}
                            onChange={setPayerAccountId}
                        />

                        <AccountSelector
                            label="Category (Destination)"
                            usage="destination"
                            accounts={accounts}
                            value={destinationAccountId}
                            onChange={setDestinationAccountId}
                        />

                        <Field>
                            <FieldLabel>Split Rule</FieldLabel>
                            <ToggleGroup
                                type="single"
                                value={splitRule}
                                onValueChange={handleSplitRuleChange}
                                variant="outline"
                                className="grid w-full grid-cols-2 gap-1 sm:grid-cols-4"
                            >
                                {SPLIT_RULE_OPTIONS.map((rule) => (
                                    <ToggleGroupItem key={rule.value} value={rule.value} className="px-2 text-xs">
                                        {rule.label}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                            <FieldDescription>{splitHelper}</FieldDescription>
                        </Field>
                    </FieldGroup>

                    <div className="flex flex-col gap-2 rounded-lg border bg-muted/30 p-3">
                        <div className="flex items-center justify-between text-xs font-semibold text-muted-foreground">
                            <span>Participant Split Breakdown</span>
                            <Badge variant="outline" className="text-[10px]">
                                {SPLIT_RULE_OPTIONS.find((o) => o.value === splitRule)?.label ?? splitRule}
                            </Badge>
                        </div>

                        {isLoadingMembers ? (
                            <div className="flex items-center justify-center p-4">
                                <Spinner className="text-muted-foreground" />
                            </div>
                        ) : activeMembers.length === 0 ? (
                            <p className="py-2 text-center text-xs text-muted-foreground">
                                No members found in this Space.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-2 text-xs">
                                {activeMembers.map((member) => {
                                    const shareAmt = previewAllocations[member.id] ?? 0;
                                    const ratio =
                                        totalShareableIncome > 0
                                            ? member.shareable_income / totalShareableIncome
                                            : activeMembers.length > 0
                                              ? 1 / activeMembers.length
                                              : 0;
                                    const isManualSelected = manualSelectedIds.has(member.id);
                                    const isIndividualSelected = individualUserId === member.id;

                                    return (
                                        <div
                                            key={member.id}
                                            className="flex items-center justify-between gap-2 border-b border-border/40 py-1.5 last:border-0"
                                        >
                                            <div className="flex min-w-0 flex-1 items-center gap-2">
                                                {splitRule === 'individual' ? (
                                                    <input
                                                        type="radio"
                                                        name="individual-participant"
                                                        className="size-4 shrink-0 accent-primary"
                                                        checked={isIndividualSelected}
                                                        onChange={() => setIndividualUserId(member.id)}
                                                        aria-label={`Assign to ${member.name}`}
                                                    />
                                                ) : null}
                                                {splitRule === 'manual' ? (
                                                    <Checkbox
                                                        checked={isManualSelected}
                                                        onCheckedChange={(checked) =>
                                                            toggleManualMember(member.id, checked === true)
                                                        }
                                                        aria-label={`Include ${member.name}`}
                                                    />
                                                ) : null}
                                                <div className="min-w-0">
                                                    <span className="font-medium text-foreground">{member.name}</span>
                                                    {splitRule === 'proportional' && (
                                                        <span className="ml-2 text-[11px] text-muted-foreground">
                                                            (Income:{' '}
                                                            {centsToCurrency(member.shareable_income, currencySymbol)} ·{' '}
                                                            {formatPercent(ratio)})
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                {splitRule === 'manual' && isManualSelected ? (
                                                    <Input
                                                        type="number"
                                                        min={0.01}
                                                        step="any"
                                                        inputMode="decimal"
                                                        aria-label={`Weight for ${member.name}`}
                                                        className="h-8 w-20 font-mono text-xs"
                                                        value={manualWeights[member.id] ?? ''}
                                                        onChange={(e) => {
                                                            const raw = e.target.value;
                                                            if (raw === '') {
                                                                setManualWeights((prev) => ({
                                                                    ...prev,
                                                                    [member.id]: 0,
                                                                }));
                                                                return;
                                                            }
                                                            const next = Number(raw);
                                                            if (!Number.isFinite(next)) return;
                                                            setManualWeights((prev) => ({
                                                                ...prev,
                                                                [member.id]: next,
                                                            }));
                                                        }}
                                                    />
                                                ) : null}
                                                <span className="w-24 text-right font-mono font-semibold text-foreground">
                                                    {splitRule === 'individual' && !isIndividualSelected
                                                        ? '—'
                                                        : splitRule === 'manual' && !isManualSelected
                                                          ? '—'
                                                          : centsToCurrency(shareAmt, currencySymbol)}
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {showRoundingNote ? (
                            <p className="text-[11px] text-muted-foreground">
                                Rounding pennies go to the last listed participant so the split always matches the
                                total.
                            </p>
                        ) : null}

                        {splitRule === 'manual' ? (
                            <p className="text-[11px] text-muted-foreground">
                                Weights are ratios (e.g. 1 and 2 → one-third / two-thirds), not currency amounts.
                            </p>
                        ) : null}

                        {!splitValid && (amountCents || 0) > 0 ? (
                            <p className="text-[11px] text-destructive">
                                {splitRule === 'individual'
                                    ? 'Select one person for this expense.'
                                    : 'Include at least one participant with a weight greater than zero.'}
                            </p>
                        ) : null}
                    </div>

                    {mutation.isError && (
                        <p className="text-xs text-destructive">
                            {(mutation.error as Error)?.message || 'Failed to create transaction.'}
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={mutation.isPending || (amountCents || 0) <= 0 || !splitValid}
                        >
                            {mutation.isPending ? <Spinner data-icon="inline-start" /> : null}
                            Save Expense
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
