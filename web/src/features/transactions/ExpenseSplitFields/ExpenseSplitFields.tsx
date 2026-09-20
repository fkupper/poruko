import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { centsToCurrency, formatPercent } from '@/lib/currency';

import { SPLIT_RULE_OPTIONS, type ExpenseSplitController } from './useExpenseSplit';

interface ExpenseSplitFieldsProps {
    split: ExpenseSplitController;
    radioGroupName?: string;
}

export function ExpenseSplitFields({
    split,
    radioGroupName = 'individual-participant',
}: ExpenseSplitFieldsProps) {
    const currencySymbol = useLedgerCurrencySymbol();

    return (
        <>
            <Field>
                <FieldLabel>Split Rule</FieldLabel>
                <ToggleGroup
                    type="single"
                    value={split.splitRule}
                    onValueChange={split.handleSplitRuleChange}
                    variant="outline"
                    className="grid w-full grid-cols-2 gap-1 sm:grid-cols-4"
                >
                    {SPLIT_RULE_OPTIONS.map((rule) => (
                        <ToggleGroupItem key={rule.value} value={rule.value} className="px-2 text-xs">
                            {rule.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <FieldDescription>{split.splitHelper}</FieldDescription>
            </Field>

            <div className="flex flex-col gap-2 rounded-lg border bg-muted/30 p-3">
                <div className="flex items-center justify-between text-xs font-semibold text-muted-foreground">
                    <span>Participant Split Breakdown</span>
                    <Badge variant="outline" className="text-[10px]">
                        {SPLIT_RULE_OPTIONS.find((option) => option.value === split.splitRule)?.label ?? split.splitRule}
                    </Badge>
                </div>

                {split.isLoadingMembers ? (
                    <div className="flex items-center justify-center p-4">
                        <Spinner className="text-muted-foreground" />
                    </div>
                ) : split.activeMembers.length === 0 ? (
                    <p className="py-2 text-center text-xs text-muted-foreground">
                        No members found in this Space.
                    </p>
                ) : (
                    <div className="flex flex-col gap-2 text-xs">
                        {split.activeMembers.map((member) => {
                            const shareAmt = split.previewAllocations[member.id] ?? 0;
                            const ratio =
                                split.totalShareableIncome > 0
                                    ? member.shareable_income / split.totalShareableIncome
                                    : split.activeMembers.length > 0
                                        ? 1 / split.activeMembers.length
                                        : 0;
                            const isManualSelected = split.manualSelectedIds.has(member.id);
                            const isIndividualSelected = split.individualUserId === member.id;

                            return (
                                <div
                                    key={member.id}
                                    className="flex items-center justify-between gap-2 border-b border-border/40 py-1.5 last:border-0"
                                >
                                    <div className="flex min-w-0 flex-1 items-center gap-2">
                                        {split.splitRule === 'individual' ? (
                                            <input
                                                type="radio"
                                                name={radioGroupName}
                                                className="size-4 shrink-0 accent-primary"
                                                checked={isIndividualSelected}
                                                onChange={() => split.setIndividualUserId(member.id)}
                                                aria-label={`Assign to ${member.name}`}
                                            />
                                        ) : null}
                                        {split.splitRule === 'manual' ? (
                                            <Checkbox
                                                checked={isManualSelected}
                                                onCheckedChange={(checked) =>
                                                    split.toggleManualMember(member.id, checked === true)
                                                }
                                                aria-label={`Include ${member.name}`}
                                            />
                                        ) : null}
                                        <div className="min-w-0">
                                            <span className="font-medium text-foreground">{member.name}</span>
                                            {split.splitRule === 'proportional' && (
                                                <span className="ml-2 text-[11px] text-muted-foreground">
                                                    (Income:{' '}
                                                    {centsToCurrency(member.shareable_income, currencySymbol)} ·{' '}
                                                    {formatPercent(ratio)})
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        {split.splitRule === 'manual' && isManualSelected ? (
                                            <Input
                                                type="number"
                                                min={0.01}
                                                step="any"
                                                inputMode="decimal"
                                                aria-label={`Weight for ${member.name}`}
                                                className="h-8 w-20 font-mono text-xs"
                                                value={split.manualWeights[member.id] ?? ''}
                                                onChange={(event) => {
                                                    const raw = event.target.value;
                                                    if (raw === '') {
                                                        split.setManualWeights((previous) => ({
                                                            ...previous,
                                                            [member.id]: 0,
                                                        }));
                                                        return;
                                                    }
                                                    const next = Number(raw);
                                                    if (!Number.isFinite(next)) return;
                                                    split.setManualWeights((previous) => ({
                                                        ...previous,
                                                        [member.id]: next,
                                                    }));
                                                }}
                                            />
                                        ) : null}
                                        <span className="w-24 text-right font-mono font-semibold text-foreground">
                                            {split.splitRule === 'individual' && !isIndividualSelected
                                                ? '—'
                                                : split.splitRule === 'manual' && !isManualSelected
                                                    ? '—'
                                                    : centsToCurrency(shareAmt, currencySymbol)}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {split.showRoundingNote ? (
                    <p className="text-[11px] text-muted-foreground">
                        Rounding pennies go to the last listed participant so the split always matches the
                        total.
                    </p>
                ) : null}

                {split.splitRule === 'manual' ? (
                    <p className="text-[11px] text-muted-foreground">
                        Weights are ratios (e.g. 1 and 2 → one-third / two-thirds), not currency amounts.
                    </p>
                ) : null}

                {!split.splitValid && (split.amountCents || 0) > 0 ? (
                    <p className="text-[11px] text-destructive">
                        {split.splitRule === 'individual'
                            ? 'Select one person for this expense.'
                            : 'Include at least one participant with a weight greater than zero.'}
                    </p>
                ) : null}
            </div>
        </>
    );
}
