import * as React from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useForm, Controller, useFieldArray } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { createLedger } from '@/api/ledgers';
import { fetchCurrencies } from '@/api/currencies';
import { updateFinancialProfile } from '@/api/finances';
import { fetchAccounts, createAccount, updateAccount } from '@/api/accounts';
import type { IncomeOrDeductionItem, Ledger } from '@/api/types';
import { useAuthStore } from '@/stores/authStore';
import { useLedgerStore } from '@/stores/ledgerStore';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldLabel, FieldError, FieldGroup, FieldContent } from '@/components/ui/field';
import { centsToCurrency } from '@/lib/currency';
import {
    ArrowRightIcon,
    CheckCircle2Icon,
    CoinsIcon,
    HandshakeIcon,
    LandmarkIcon,
    Loader2Icon,
    PiggyBankIcon,
    PlusIcon,
    ReceiptIcon,
    SparklesIcon,
    Trash2Icon,
    UserIcon,
    WalletIcon,
} from 'lucide-react';

const step1Schema = z.object({
    spaceName: z.string().min(1, 'Space name is required'),
    currencyCode: z.string().min(1),
    settlementMode: z.enum(['joint_clearinghouse', 'direct_p2p']),
    settlementTimezone: z.string().min(1, 'Timezone is required'),
    settlementCutoffDay: z.number().int().min(1).max(31),
    settlementAutoExecuteEnabled: z.boolean(),
});

const step2Schema = z.object({
    incomeDescription: z.string().min(1, 'Description is required'),
    incomeCents: z.number().min(0),
    deductions: z.array(z.object({
        description: z.string().min(1, 'Required'),
        amount: z.number().min(0),
    })),
});

const step3Schema = z.object({
    accountName: z.string().min(1, 'Account name is required'),
    accountBalanceCents: z.number(),
});

type Step1Values = z.infer<typeof step1Schema>;
type Step2Values = z.infer<typeof step2Schema>;
type Step3Values = z.infer<typeof step3Schema>;

export function SetupWizard() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const currentUser = useAuthStore((s) => s.user);
    const setActiveLedgerId = useLedgerStore((s) => s.setActiveLedgerId);

    const [currentStep, setCurrentStep] = React.useState<1 | 2 | 3 | 4>(1);
    const [createdLedger, setCreatedLedger] = React.useState<Ledger | null>(null);

    // Fetch backend Source of Truth currencies and Docker default preset
    const { data: currenciesData } = useQuery({
        queryKey: ['currencies'],
        queryFn: fetchCurrencies,
    });

    const currencies = React.useMemo(() => currenciesData?.available ?? [
        { code: 'EUR', symbol: '€', name: 'Euro' },
        { code: 'USD', symbol: '$', name: 'US Dollar' },
        { code: 'GBP', symbol: '£', name: 'British Pound' },
    ], [currenciesData]);

    const form1 = useForm<Step1Values>({
        resolver: zodResolver(step1Schema),
        defaultValues: {
            spaceName: '',
            currencyCode: 'EUR',
            settlementMode: 'joint_clearinghouse',
            settlementTimezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
            settlementCutoffDay: 31,
            settlementAutoExecuteEnabled: false,
        }
    });

    const form2 = useForm<Step2Values>({
        resolver: zodResolver(step2Schema),
        defaultValues: {
            incomeDescription: 'Base Salary',
            incomeCents: 100000,
            deductions: [],
        }
    });

    const { fields: deductionFields, append: appendDeduction, remove: removeDeduction } = useFieldArray({
        control: form2.control,
        name: 'deductions'
    });

    const form3 = useForm<Step3Values>({
        resolver: zodResolver(step3Schema),
        defaultValues: {
            accountName: 'Personal Checking',
            accountBalanceCents: 0,
        }
    });

    // Preset default currency when backend default (e.g. from Docker env) arrives
    React.useEffect(() => {
        if (currenciesData?.default && !form1.formState.isDirty) {
            form1.setValue('currencyCode', currenciesData.default);
        }
    }, [currenciesData, form1]);

    const watchedCurrencyCode = form1.watch('currencyCode');
    const activeCurrencySymbol = React.useMemo(() => {
        const match = currencies.find((c) => c.code === watchedCurrencyCode);
        return match?.symbol ?? '€';
    }, [currencies, watchedCurrencyCode]);

    const incomeCents = form2.watch('incomeCents');
    const totalDeductionsCents = form2.watch('deductions').reduce((sum, d) => sum + Math.max(0, d.amount || 0), 0);
    const netCapacityCents = Math.max(0, incomeCents - totalDeductionsCents);

    // Step 3 state: Fetch user's automatically initialized personal account
    const { data: existingAccounts } = useQuery({
        queryKey: ['accounts', createdLedger?.id],
        queryFn: () => fetchAccounts(createdLedger!.id),
        enabled: !!createdLedger?.id && currentStep === 3,
    });

    const userPersonalAccount = React.useMemo(() => {
        if (!existingAccounts) return null;
        return (
            existingAccounts.find((a) => a.type === 'user_funding' && a.owner_id === currentUser?.id) ||
            existingAccounts.find((a) => a.type === 'user_funding') ||
            null
        );
    }, [existingAccounts, currentUser]);

    // Pre-populate Step 3 fields with existing account details when available
    React.useEffect(() => {
        if (userPersonalAccount && !form3.formState.isDirty) {
            form3.reset({
                accountName: userPersonalAccount.name,
                accountBalanceCents: userPersonalAccount.base_budget ?? userPersonalAccount.balance ?? 0,
            });
        }
    }, [userPersonalAccount, form3]);

    // Mutation 1: Create Space
    const createSpaceMutation = useMutation({
        mutationFn: async (values: Step1Values) => {
            return createLedger({
                name: values.spaceName.trim() || 'Our Household',
                currency: values.currencyCode,
                settlement_mode: values.settlementMode,
                settlement_timezone: values.settlementTimezone,
                settlement_cutoff_day: values.settlementCutoffDay,
                settlement_auto_execute_enabled: values.settlementAutoExecuteEnabled,
            });
        },
        onSuccess: (ledger) => {
            setCreatedLedger(ledger);
            setActiveLedgerId(ledger.id);
            queryClient.invalidateQueries({ queryKey: ['ledgers'] });
            setCurrentStep(2);
        },
    });

    // Mutation 2: Save Profile
    const profileMutation = useMutation({
        mutationFn: async (values: Step2Values) => {
            if (!createdLedger || !currentUser) throw new Error('Space or user missing');
            const description = values.incomeDescription.trim() || 'Base Salary';
            const amount = Math.max(0, values.incomeCents);
            const incomes: IncomeOrDeductionItem[] = [
                { description, amount }
            ];

            const cleanedDeductions: IncomeOrDeductionItem[] = values.deductions
                .map((d) => ({
                    description: d.description.trim() || 'Fixed Commitment',
                    amount: Math.max(0, d.amount),
                }));

            return updateFinancialProfile(createdLedger.id, currentUser.id, {
                incomes,
                deductions: cleanedDeductions,
            });
        },
        onSuccess: () => {
            setCurrentStep(3);
        },
    });

    // Mutation 3: Update existing auto-created Personal Account
    const accountMutation = useMutation({
        mutationFn: async (values: Step3Values) => {
            if (!createdLedger) throw new Error('Space missing');
            const name = values.accountName.trim() || 'Personal Checking';
            if (userPersonalAccount) {
                return updateAccount(createdLedger.id, userPersonalAccount.id, {
                    name,
                    current_funds: values.accountBalanceCents,
                });
            }
            return createAccount(createdLedger.id, {
                name,
                type: 'user_funding',
                balance: values.accountBalanceCents,
            });
        },
        onSuccess: () => {
            setCurrentStep(4);
        },
    });

    const handleFinish = () => {
        if (createdLedger) {
            setActiveLedgerId(createdLedger.id);
        }
        queryClient.invalidateQueries();
        navigate('/');
    };

    const getErrorMessage = (error: unknown): string => {
        const axiosErr = error as { response?: { data?: { message?: string } } };
        return axiosErr.response?.data?.message || (error as Error)?.message || 'Request failed. Please try again.';
    };

    return (
        <Card className="w-full max-w-xl shadow-lg border border-border bg-card">
            {/* Stepper Header */}
            <CardHeader className="border-b border-border bg-muted/20 pb-4">
                <div className="flex items-center justify-between text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                    <span>Onboarding Wizard</span>
                    <span>Step {currentStep} of 4</span>
                </div>
                <div className="flex items-center gap-2">
                    {[1, 2, 3, 4].map((step) => (
                        <div
                            key={step}
                            className={`h-1.5 flex-1 rounded-full transition-colors ${
                                step <= currentStep ? 'bg-primary' : 'bg-muted'
                            }`}
                        />
                    ))}
                </div>
            </CardHeader>

            <CardContent className="pt-6 space-y-5">
                {/* STEP 1: Create Space & Primary Currency */}
                {currentStep === 1 && (
                    <form id="step1-form" onSubmit={form1.handleSubmit((v) => createSpaceMutation.mutate(v))} className="space-y-4">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                <PiggyBankIcon className="size-5 text-primary" />
                                Create Your First Space
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Set up your household name, primary currency, and settlement mode.
                            </p>
                        </div>

                        <FieldGroup className="grid grid-cols-3 gap-3">
                            <div className="col-span-2">
                                <Controller
                                    control={form1.control}
                                    name="spaceName"
                                    render={({ field, fieldState }) => (
                                        <Field>
                                            <FieldLabel>Space Name</FieldLabel>
                                            <FieldContent>
                                                <Input {...field} placeholder="e.g. Our Home, Apartment 4B" className="bg-transparent" />
                                            </FieldContent>
                                            <FieldError errors={[fieldState.error]} />
                                        </Field>
                                    )}
                                />
                            </div>

                            <div>
                                <Controller
                                    control={form1.control}
                                    name="currencyCode"
                                    render={({ field, fieldState }) => (
                                        <Field>
                                            <FieldLabel className="flex items-center gap-1">
                                                <CoinsIcon className="size-3.5" />
                                                Currency
                                            </FieldLabel>
                                            <FieldContent>
                                                <Select value={field.value} onValueChange={field.onChange}>
                                                    <SelectTrigger className="font-mono bg-card">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {currencies.map((c) => (
                                                            <SelectItem key={c.code} value={c.code}>
                                                                {c.code} ({c.symbol})
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </FieldContent>
                                            <FieldError errors={[fieldState.error]} />
                                        </Field>
                                    )}
                                />
                            </div>
                        </FieldGroup>

                        <div>
                            <Controller
                                control={form1.control}
                                name="settlementMode"
                                render={({ field }) => (
                                    <Field>
                                        <FieldLabel>Settlement Mode</FieldLabel>
                                        <FieldContent className="grid grid-cols-2 gap-3 mt-1">
                                            <button
                                                type="button"
                                                onClick={() => field.onChange('joint_clearinghouse')}
                                                className={`p-3 rounded-xl border text-left flex flex-col gap-2 transition-all ${
                                                    field.value === 'joint_clearinghouse'
                                                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                                        : 'bg-card hover:bg-muted/50'
                                                }`}
                                            >
                                                <LandmarkIcon className="size-5 text-inflow" />
                                                <div>
                                                    <div className="text-xs font-semibold text-foreground">
                                                        Joint Clearinghouse
                                                    </div>
                                                    <div className="text-[11px] text-muted-foreground mt-0.5">
                                                        Refill a shared house pool account at settlement.
                                                    </div>
                                                </div>
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => field.onChange('direct_p2p')}
                                                className={`p-3 rounded-xl border text-left flex flex-col gap-2 transition-all ${
                                                    field.value === 'direct_p2p'
                                                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                                        : 'bg-card hover:bg-muted/50'
                                                }`}
                                            >
                                                <HandshakeIcon className="size-5 text-info" />
                                                <div>
                                                    <div className="text-xs font-semibold text-foreground">
                                                        Direct P2P
                                                    </div>
                                                    <div className="text-[11px] text-muted-foreground mt-0.5">
                                                        Direct peer-to-peer transfers between members.
                                                    </div>
                                                </div>
                                            </button>
                                        </FieldContent>
                                    </Field>
                                )}
                            />
                        </div>

                        <div className="space-y-3 pt-3 border-t border-border">
                            <h3 className="text-sm font-semibold text-foreground">Settlement Automation</h3>
                            <FieldGroup className="grid grid-cols-2 gap-3">
                                <div>
                                    <Controller
                                        control={form1.control}
                                        name="settlementTimezone"
                                        render={({ field, fieldState }) => (
                                            <Field>
                                                <FieldLabel>Timezone</FieldLabel>
                                                <FieldContent>
                                                    <Input {...field} className="bg-transparent" />
                                                </FieldContent>
                                                <FieldError errors={[fieldState.error]} />
                                            </Field>
                                        )}
                                    />
                                </div>
                                <div>
                                    <Controller
                                        control={form1.control}
                                        name="settlementCutoffDay"
                                        render={({ field, fieldState }) => (
                                            <Field>
                                                <FieldLabel>Cutoff Day</FieldLabel>
                                                <FieldContent>
                                                    <Select value={String(field.value)} onValueChange={(v) => field.onChange(Number(v))}>
                                                        <SelectTrigger className="bg-card">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="1">1st of the month</SelectItem>
                                                            <SelectItem value="15">15th of the month</SelectItem>
                                                            <SelectItem value="31">Last day of the month</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </FieldContent>
                                                <FieldError errors={[fieldState.error]} />
                                            </Field>
                                        )}
                                    />
                                </div>
                            </FieldGroup>
                            
                            <Controller
                                control={form1.control}
                                name="settlementAutoExecuteEnabled"
                                render={({ field }) => (
                                    <Field orientation="horizontal" className="items-start gap-2 rounded-lg border p-3 cursor-pointer hover:bg-muted/50 transition-colors">
                                        <Checkbox 
                                            checked={field.value}
                                            onCheckedChange={field.onChange}
                                            className="mt-1"
                                        />
                                        <div>
                                            <div className="text-sm font-medium text-foreground">Auto-Approve & Execute</div>
                                            <div className="text-[11px] text-muted-foreground leading-relaxed mt-0.5">
                                                Enabling this will automatically close the period. This carries an operational risk of falling out of sync with real-world accounts if mistakes occur.
                                            </div>
                                        </div>
                                    </Field>
                                )}
                            />
                        </div>

                        {createSpaceMutation.isError && (
                            <p className="text-xs text-destructive">
                                {getErrorMessage(createSpaceMutation.error)}
                            </p>
                        )}
                    </form>
                )}

                {/* STEP 2: Financial Profile & Fixed Deductions */}
                {currentStep === 2 && (
                    <form id="step2-form" onSubmit={form2.handleSubmit((v) => profileMutation.mutate(v))} className="space-y-5">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                <UserIcon className="size-5 text-primary" />
                                Setup Your Financial Capacity
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Set your primary monthly income and optional fixed commitments to compute proportional expense splits.
                            </p>
                        </div>

                        {/* Income Source */}
                        <FieldGroup className="grid grid-cols-2 gap-3">
                            <Controller
                                control={form2.control}
                                name="incomeDescription"
                                render={({ field, fieldState }) => (
                                    <Field>
                                        <FieldLabel>Primary Income Source</FieldLabel>
                                        <FieldContent>
                                            <Input {...field} placeholder="e.g. Base Salary" className="bg-transparent" />
                                        </FieldContent>
                                        <FieldError errors={[fieldState.error]} />
                                    </Field>
                                )}
                            />
                            <Controller
                                control={form2.control}
                                name="incomeCents"
                                render={({ field, fieldState }) => (
                                    <Field>
                                        <FieldLabel>Monthly Income ({activeCurrencySymbol})</FieldLabel>
                                        <FieldContent>
                                            <CurrencyInput
                                                value={field.value}
                                                onCentsChange={field.onChange}
                                                currencySymbol={activeCurrencySymbol}
                                            />
                                        </FieldContent>
                                        <FieldError errors={[fieldState.error]} />
                                    </Field>
                                )}
                            />
                        </FieldGroup>

                        {/* Fixed Deductions & Commitments (Optional) */}
                        <div className="space-y-3 border-t border-border pt-4">
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                        <ReceiptIcon className="size-4 text-amber-500" />
                                        Fixed Deductions & Commitments
                                        <span className="text-[11px] font-normal text-muted-foreground">(Optional)</span>
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Personal expenses (taxes, insurance, loans) deducted before split calculations.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => appendDeduction({ description: '', amount: 0 })}
                                    className="gap-1 h-8 text-xs bg-background"
                                >
                                    <PlusIcon className="size-3.5" />
                                    Add Deduction
                                </Button>
                            </div>

                            {deductionFields.length === 0 ? (
                                <div className="rounded-lg border border-dashed border-border p-3 text-center text-xs text-muted-foreground">
                                    No fixed deductions added yet. Click <strong>+ Add Deduction</strong> if you have monthly commitments.
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {deductionFields.map((field, idx) => (
                                        <div key={field.id} className="flex items-center gap-2">
                                            <Controller
                                                control={form2.control}
                                                name={`deductions.${idx}.description`}
                                                render={({ field: inputField }) => (
                                                    <Input {...inputField} placeholder="e.g. Health Insurance, Taxes" className="h-9 flex-1 bg-transparent text-xs" />
                                                )}
                                            />
                                            <Controller
                                                control={form2.control}
                                                name={`deductions.${idx}.amount`}
                                                render={({ field: amountField }) => (
                                                    <div className="w-36">
                                                        <CurrencyInput
                                                            value={amountField.value}
                                                            onCentsChange={amountField.onChange}
                                                            currencySymbol={activeCurrencySymbol}
                                                        />
                                                    </div>
                                                )}
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeDeduction(idx)}
                                                className="size-9 text-muted-foreground hover:text-destructive shrink-0"
                                            >
                                                <Trash2Icon className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Capacity Summary Preview */}
                        <div className="rounded-lg border border-border bg-muted/30 p-3 text-xs flex items-center justify-between text-muted-foreground">
                            <span>Net Shareable Capacity:</span>
                            <span className="font-semibold text-foreground text-sm">
                                {centsToCurrency(netCapacityCents, activeCurrencySymbol)}
                            </span>
                        </div>

                        {profileMutation.isError && (
                            <p className="text-xs text-destructive">
                                {getErrorMessage(profileMutation.error)}
                            </p>
                        )}
                    </form>
                )}

                {/* STEP 3: Payment Account (Updating auto-created personal account) */}
                {currentStep === 3 && (
                    <form id="step3-form" onSubmit={form3.handleSubmit((v) => accountMutation.mutate(v))} className="space-y-4">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                <WalletIcon className="size-5 text-primary" />
                                Personal Account Setup
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Update the name and starting balance for your primary payment account.
                            </p>
                        </div>

                        <FieldGroup>
                            <Controller
                                control={form3.control}
                                name="accountName"
                                render={({ field, fieldState }) => (
                                    <Field>
                                        <FieldLabel>Account Name</FieldLabel>
                                        <FieldContent>
                                            <Input {...field} placeholder="e.g. Personal Checking, Revolut" className="bg-transparent" />
                                        </FieldContent>
                                        <FieldError errors={[fieldState.error]} />
                                    </Field>
                                )}
                            />
                            <Controller
                                control={form3.control}
                                name="accountBalanceCents"
                                render={({ field, fieldState }) => (
                                    <Field>
                                        <FieldLabel>Initial Balance ({activeCurrencySymbol})</FieldLabel>
                                        <FieldContent>
                                            <CurrencyInput
                                                value={field.value}
                                                onCentsChange={field.onChange}
                                                currencySymbol={activeCurrencySymbol}
                                            />
                                        </FieldContent>
                                        <FieldError errors={[fieldState.error]} />
                                    </Field>
                                )}
                            />
                        </FieldGroup>

                        {accountMutation.isError && (
                            <p className="text-xs text-destructive">
                                {getErrorMessage(accountMutation.error)}
                            </p>
                        )}
                    </form>
                )}

                {/* STEP 4: All Set */}
                {currentStep === 4 && (
                    <div className="space-y-4 text-center py-4">
                        <div className="size-12 rounded-full bg-inflow/15 text-inflow mx-auto flex items-center justify-center">
                            <CheckCircle2Icon className="size-7" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold tracking-tight text-foreground">
                                You're All Set!
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Your space <strong>"{createdLedger?.name || form1.watch('spaceName') || 'Our Household'}"</strong> is ready to use.
                            </p>
                        </div>

                        <div className="rounded-xl border border-border bg-muted/20 p-4 text-xs space-y-2 text-left max-w-md mx-auto font-mono">
                            <div className="flex justify-between border-b border-border pb-1">
                                <span className="text-muted-foreground">Space:</span>
                                <span className="font-semibold">{createdLedger?.name}</span>
                            </div>
                            <div className="flex justify-between border-b border-border pb-1">
                                <span className="text-muted-foreground">Currency:</span>
                                <span className="font-semibold">{form1.watch('currencyCode')} ({activeCurrencySymbol})</span>
                            </div>
                            <div className="flex justify-between border-b border-border pb-1">
                                <span className="text-muted-foreground">Gross Income:</span>
                                <span className="font-semibold text-inflow">{centsToCurrency(incomeCents, activeCurrencySymbol)}</span>
                            </div>
                            {totalDeductionsCents > 0 && (
                                <div className="flex justify-between border-b border-border pb-1">
                                    <span className="text-muted-foreground">Fixed Deductions:</span>
                                    <span className="font-semibold text-destructive">-{centsToCurrency(totalDeductionsCents, activeCurrencySymbol)}</span>
                                </div>
                            )}
                            <div className="flex justify-between border-b border-border pb-1">
                                <span className="text-muted-foreground">Net Shareable Capacity:</span>
                                <span className="font-semibold text-foreground">{centsToCurrency(netCapacityCents, activeCurrencySymbol)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Payment Account:</span>
                                <span className="font-semibold">{form3.watch('accountName')}</span>
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>

            <CardFooter className="flex items-center justify-between border-t border-border bg-muted/10 pt-4">
                {currentStep === 1 && (
                    <div className="text-xs text-muted-foreground flex items-center gap-1">
                        <SparklesIcon className="size-3.5 text-primary" />
                        Step 1 of 4
                    </div>
                )}

                {currentStep === 2 && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setCurrentStep(3)}
                    >
                        Skip for now
                    </Button>
                )}

                {currentStep === 3 && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setCurrentStep(4)}
                    >
                        Skip for now
                    </Button>
                )}

                {currentStep === 4 && <div />}

                {currentStep === 1 && (
                    <Button
                        type="submit"
                        form="step1-form"
                        disabled={createSpaceMutation.isPending}
                        className="gap-2 ml-auto"
                    >
                        {createSpaceMutation.isPending ? (
                            <Loader2Icon className="size-4 animate-spin" />
                        ) : (
                            <ArrowRightIcon className="size-4" />
                        )}
                        Continue
                    </Button>
                )}

                {currentStep === 2 && (
                    <Button
                        type="submit"
                        form="step2-form"
                        disabled={profileMutation.isPending}
                        className="gap-2 ml-auto"
                    >
                        {profileMutation.isPending ? (
                            <Loader2Icon className="size-4 animate-spin" />
                        ) : (
                            <ArrowRightIcon className="size-4" />
                        )}
                        Continue
                    </Button>
                )}

                {currentStep === 3 && (
                    <Button
                        type="submit"
                        form="step3-form"
                        disabled={accountMutation.isPending}
                        className="gap-2 ml-auto"
                    >
                        {accountMutation.isPending ? (
                            <Loader2Icon className="size-4 animate-spin" />
                        ) : (
                            <ArrowRightIcon className="size-4" />
                        )}
                        Continue
                    </Button>
                )}

                {currentStep === 4 && (
                    <Button onClick={handleFinish} className="gap-2 w-full sm:w-auto">
                        Go to Dashboard
                        <ArrowRightIcon className="size-4" />
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
}
