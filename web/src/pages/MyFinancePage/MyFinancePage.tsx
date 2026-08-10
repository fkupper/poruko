import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchFinancialProfile, updateFinancialProfile } from '@/api/finances';
import type { IncomeOrDeductionItem } from '@/api/types';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { useAuthStore } from '@/stores/authStore';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PlusIcon, Trash2Icon, UserIcon, SaveIcon, CheckCircle2Icon } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import { Skeleton } from '@/components/ui/skeleton';

export default function MyFinancePage() {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const currencySymbol = useLedgerCurrencySymbol();
    const currentUser = useAuthStore((s) => s.user);

    const [incomes, setIncomes] = React.useState<IncomeOrDeductionItem[]>([{ description: '', amount: 0 }]);
    const [deductions, setDeductions] = React.useState<IncomeOrDeductionItem[]>([]);
    const [saveSuccess, setSaveSuccess] = React.useState(false);
    const [profileRevision, setProfileRevision] = React.useState<string | null>(null);

    // Fetch user active financial profile dynamically
    const { data: profile, isPending } = useQuery({
        queryKey: ['financial-profile', activeLedgerId, currentUser?.id],
        queryFn: () => fetchFinancialProfile(activeLedgerId!, currentUser!.id),
        enabled: !!activeLedgerId && !!currentUser?.id,
        retry: false,
    });

    const nextRevision = profile
        ? `${activeLedgerId}:${currentUser?.id}:${JSON.stringify(profile.incomes)}:${JSON.stringify(profile.deductions)}`
        : activeLedgerId
          ? `${activeLedgerId}:empty`
          : null;

    // Reset local draft when the server profile (or space) changes — render-time adjust.
    if (nextRevision !== profileRevision) {
        setProfileRevision(nextRevision);
        if (profile) {
            setIncomes(
                profile.incomes && profile.incomes.length > 0
                    ? profile.incomes
                    : [{ description: '', amount: 0 }],
            );
            setDeductions(profile.deductions && profile.deductions.length > 0 ? profile.deductions : []);
        } else {
            setIncomes([{ description: '', amount: 0 }]);
            setDeductions([]);
        }
    }

    // Compute totals in real-time
    const totalIncome = React.useMemo(
        () => incomes.reduce((sum, item) => sum + (item.amount || 0), 0),
        [incomes]
    );

    const totalDeductions = React.useMemo(
        () => deductions.reduce((sum, item) => sum + (item.amount || 0), 0),
        [deductions]
    );

    const shareableIncome = React.useMemo(
        () => Math.max(0, totalIncome - totalDeductions),
        [totalIncome, totalDeductions]
    );

    const mutation = useMutation({
        mutationFn: async () => {
            if (!activeLedgerId || !currentUser) throw new Error('Not authenticated or no space selected.');
            return updateFinancialProfile(activeLedgerId, currentUser.id, {
                incomes,
                deductions,
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['financial-profile', activeLedgerId, currentUser?.id] });
            queryClient.invalidateQueries({ queryKey: ['members', activeLedgerId] });
            setSaveSuccess(true);
            setTimeout(() => setSaveSuccess(false), 3000);
        },
    });

    // Handlers for Incomes
    const addIncome = () => setIncomes((prev) => [...prev, { description: '', amount: 0 }]);
    const updateIncome = (index: number, field: keyof IncomeOrDeductionItem, value: string | number) => {
        setIncomes((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], [field]: value };
            return next;
        });
    };
    const removeIncome = (index: number) =>
        setIncomes((prev) => prev.filter((_, i) => i !== index));

    // Handlers for Deductions
    const addDeduction = () => setDeductions((prev) => [...prev, { description: '', amount: 0 }]);
    const updateDeduction = (index: number, field: keyof IncomeOrDeductionItem, value: string | number) => {
        setDeductions((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], [field]: value };
            return next;
        });
    };
    const removeDeduction = (index: number) =>
        setDeductions((prev) => prev.filter((_, i) => i !== index));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        mutation.mutate();
    };

    if (isPending) {
        return (
            <div className="flex flex-col gap-4">
                <Skeleton className="h-10 w-64" />
                <div className="grid gap-4 md:grid-cols-3">
                    <Skeleton className="h-24 rounded-xl" />
                    <Skeleton className="h-24 rounded-xl" />
                    <Skeleton className="h-24 rounded-xl" />
                </div>
                <Skeleton className="h-48 rounded-xl" />
            </div>
        );
    }

    return (
        <div className="space-y-6 w-full">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <UserIcon className="size-6 text-primary" />
                        My Financial Profile {currentUser?.name ? `(${currentUser.name})` : ''}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage your income streams and deductions to calculate your dynamic proportional split ratio.
                    </p>
                </div>
                <Button onClick={handleSubmit} disabled={mutation.isPending} className="gap-2 shrink-0">
                    {mutation.isPending ? (
                        <Spinner data-icon="inline-start" />
                    ) : saveSuccess ? (
                        <CheckCircle2Icon data-icon="inline-start" className="text-inflow" />
                    ) : (
                        <SaveIcon data-icon="inline-start" />
                    )}
                    {saveSuccess ? 'Saved!' : 'Save Profile'}
                </Button>
            </div>

            {/* Computed Capacity Card */}
            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-xs font-medium text-muted-foreground">
                            Total Monthly Income
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold font-mono text-inflow">
                            {centsToCurrency(totalIncome, currencySymbol)}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-xs font-medium text-muted-foreground">
                            Total Deductions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold font-mono text-outflow">
                            {centsToCurrency(totalDeductions, currencySymbol)}
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-2 border-primary/20 bg-primary/5">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-xs font-semibold text-primary">
                            Shareable Income Capacity
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold font-mono text-foreground">
                            {centsToCurrency(shareableIncome, currencySymbol)}
                        </div>
                        <p className="text-[11px] text-muted-foreground mt-1">
                            Used for proportional expense splits
                        </p>
                    </CardContent>
                </Card>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Incomes Array */}
                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-base font-semibold text-foreground">
                                Income Sources
                            </CardTitle>
                            <Button type="button" variant="outline" size="sm" onClick={addIncome} className="gap-1 text-xs">
                                <PlusIcon data-icon="inline-start" />
                                Add Income
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {incomes.map((item, idx) => (
                            <div key={idx} className="flex items-center gap-3">
                                <Input
                                    type="text"
                                    placeholder="Source (e.g. Salary, Freelance)"
                                    value={item.description}
                                    onChange={(e) => updateIncome(idx, 'description', e.target.value)}
                                    className="h-10 flex-1"
                                />
                                <div className="w-40">
                                    <CurrencyInput
                                        value={item.amount}
                                        onCentsChange={(cents) => updateIncome(idx, 'amount', cents || 0)}
                                        currencySymbol={currencySymbol}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removeIncome(idx)}
                                    disabled={incomes.length <= 1}
                                    aria-label={`Remove income ${item.description || idx + 1}`}
                                    className="text-muted-foreground hover:text-destructive shrink-0"
                                >
                                    <Trash2Icon />
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {/* Deductions Array */}
                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-base font-semibold text-foreground">
                                Fixed Deductions & Commitments
                            </CardTitle>
                            <Button type="button" variant="outline" size="sm" onClick={addDeduction} className="gap-1 text-xs">
                                <PlusIcon data-icon="inline-start" />
                                Add Deduction
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {deductions.map((item, idx) => (
                            <div key={idx} className="flex items-center gap-3">
                                <Input
                                    type="text"
                                    placeholder="Deduction (e.g. Student Loan, Health Insurance)"
                                    value={item.description}
                                    onChange={(e) => updateDeduction(idx, 'description', e.target.value)}
                                    className="h-10 flex-1"
                                />
                                <div className="w-40">
                                    <CurrencyInput
                                        value={item.amount}
                                        onCentsChange={(cents) => updateDeduction(idx, 'amount', cents || 0)}
                                        currencySymbol={currencySymbol}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removeDeduction(idx)}
                                    aria-label={`Remove deduction ${item.description || idx + 1}`}
                                    className="text-muted-foreground hover:text-destructive shrink-0"
                                >
                                    <Trash2Icon />
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {mutation.isError && (
                    <p className="text-xs text-destructive">
                        {(mutation.error as Error)?.message || 'Failed to update financial profile.'}
                    </p>
                )}
            </form>
        </div>
    );
}
