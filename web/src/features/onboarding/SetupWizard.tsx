import * as React from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
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

    // Step 1 state
    const [spaceName, setSpaceName] = React.useState('');
    const [currencyCode, setCurrencyCode] = React.useState('EUR');
    const [settlementMode, setSettlementMode] = React.useState<'joint_clearinghouse' | 'direct_p2p'>(
        'joint_clearinghouse'
    );

    // Preset default currency when backend default (e.g. from Docker env) arrives
    React.useEffect(() => {
        if (currenciesData?.default) {
            setCurrencyCode(currenciesData.default);
        }
    }, [currenciesData]);

    const activeCurrencySymbol = React.useMemo(() => {
        const match = currencies.find((c) => c.code === currencyCode);
        return match?.symbol ?? '€';
    }, [currencies, currencyCode]);

    // Step 2 state: Impersonal suggestion 1,000 = 100000 cents
    const [incomeDescription, setIncomeDescription] = React.useState('Base Salary');
    const [incomeCents, setIncomeCents] = React.useState(100000);
    const [deductions, setDeductions] = React.useState<IncomeOrDeductionItem[]>([]);

    const handleAddDeduction = () => {
        setDeductions((prev) => [
            ...prev,
            { description: '', amount: 0 },
        ]);
    };

    const handleUpdateDeduction = (index: number, field: 'description' | 'amount', value: string | number) => {
        setDeductions((prev) => {
            const next = [...prev];
            next[index] = { ...next[index], [field]: value };
            return next;
        });
    };

    const handleRemoveDeduction = (index: number) => {
        setDeductions((prev) => prev.filter((_, i) => i !== index));
    };

    const totalDeductionsCents = React.useMemo(() => {
        return deductions.reduce((sum, d) => sum + Math.max(0, d.amount), 0);
    }, [deductions]);

    const netCapacityCents = React.useMemo(() => {
        return Math.max(0, incomeCents - totalDeductionsCents);
    }, [incomeCents, totalDeductionsCents]);

    // Step 3 state: Fetch user's automatically initialized personal account
    const { data: existingAccounts } = useQuery({
        queryKey: ['accounts', createdLedger?.id],
        queryFn: () => fetchAccounts(createdLedger!.id),
        enabled: !!createdLedger?.id && currentStep === 3,
    });

    const userPersonalAccount = React.useMemo(() => {
        if (!existingAccounts) return null;
        return (
            existingAccounts.find((a) => a.type === 'personal' && a.owner_id === currentUser?.id) ||
            existingAccounts.find((a) => a.type === 'personal') ||
            null
        );
    }, [existingAccounts, currentUser]);

    const [accountName, setAccountName] = React.useState('Personal Checking');
    const [accountBalanceCents, setAccountBalanceCents] = React.useState(0);

    // Pre-populate Step 3 fields with existing account details when available
    React.useEffect(() => {
        if (userPersonalAccount) {
            setAccountName(userPersonalAccount.name);
            setAccountBalanceCents(userPersonalAccount.base_budget ?? userPersonalAccount.balance ?? 0);
        }
    }, [userPersonalAccount]);

    // Mutation 1: Create Space
    const createSpaceMutation = useMutation({
        mutationFn: async () => {
            return createLedger({
                name: spaceName.trim() || 'Our Household',
                currency: currencyCode,
                settlement_mode: settlementMode,
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
        mutationFn: async () => {
            if (!createdLedger || !currentUser) throw new Error('Space or user missing');
            const description = incomeDescription.trim() || 'Base Salary';
            const amount = Math.max(0, incomeCents);
            const incomes: IncomeOrDeductionItem[] = [
                { description, amount }
            ];

            const cleanedDeductions: IncomeOrDeductionItem[] = deductions
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
        mutationFn: async () => {
            if (!createdLedger) throw new Error('Space missing');
            const name = accountName.trim() || 'Personal Checking';
            if (userPersonalAccount) {
                return updateAccount(createdLedger.id, userPersonalAccount.id, {
                    name,
                    base_budget: accountBalanceCents,
                    balance: accountBalanceCents,
                });
            }
            return createAccount(createdLedger.id, {
                name,
                type: 'personal',
                balance: accountBalanceCents,
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
        <Card className="w-full max-w-xl shadow-lg border">
            {/* Stepper Header */}
            <CardHeader className="border-b bg-muted/20 pb-4">
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
                    <div className="space-y-4">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                <PiggyBankIcon className="size-5 text-primary" />
                                Create Your First Space
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Set up your household name, primary currency, and settlement mode.
                            </p>
                        </div>

                        <div className="grid grid-cols-3 gap-3">
                            <div className="col-span-2">
                                <label className="text-xs font-medium text-muted-foreground block mb-1">
                                    Space Name
                                </label>
                                <input
                                    type="text"
                                    placeholder="e.g. Our Home, Apartment 4B"
                                    value={spaceName}
                                    onChange={(e) => setSpaceName(e.target.value)}
                                    className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-medium text-muted-foreground block mb-1 flex items-center gap-1">
                                    <CoinsIcon className="size-3.5" />
                                    Currency
                                </label>
                                <select
                                    value={currencyCode}
                                    onChange={(e) => setCurrencyCode(e.target.value)}
                                    className="h-10 w-full rounded-lg border border-input bg-card px-2 py-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring font-mono"
                                >
                                    {currencies.map((c) => (
                                        <option key={c.code} value={c.code}>
                                            {c.code} ({c.symbol})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-2">
                                Settlement Mode
                            </label>
                            <div className="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    onClick={() => setSettlementMode('joint_clearinghouse')}
                                    className={`p-3 rounded-xl border text-left flex flex-col gap-2 transition-all ${
                                        settlementMode === 'joint_clearinghouse'
                                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                            : 'bg-card hover:bg-muted/50'
                                    }`}
                                >
                                    <LandmarkIcon className="size-5 text-emerald-500" />
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
                                    onClick={() => setSettlementMode('direct_p2p')}
                                    className={`p-3 rounded-xl border text-left flex flex-col gap-2 transition-all ${
                                        settlementMode === 'direct_p2p'
                                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                            : 'bg-card hover:bg-muted/50'
                                    }`}
                                >
                                    <HandshakeIcon className="size-5 text-blue-500" />
                                    <div>
                                        <div className="text-xs font-semibold text-foreground">
                                            Direct P2P
                                        </div>
                                        <div className="text-[11px] text-muted-foreground mt-0.5">
                                            Direct peer-to-peer transfers between members.
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        {createSpaceMutation.isError && (
                            <p className="text-xs text-destructive">
                                {getErrorMessage(createSpaceMutation.error)}
                            </p>
                        )}
                    </div>
                )}

                {/* STEP 2: Financial Profile & Fixed Deductions */}
                {currentStep === 2 && (
                    <div className="space-y-5">
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
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground block mb-1">
                                    Primary Income Source
                                </label>
                                <input
                                    type="text"
                                    placeholder="e.g. Base Salary"
                                    value={incomeDescription}
                                    onChange={(e) => setIncomeDescription(e.target.value)}
                                    className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                />
                            </div>
                            <div>
                                <label className="text-xs font-medium text-muted-foreground block mb-1">
                                    Monthly Income ({activeCurrencySymbol})
                                </label>
                                <CurrencyInput
                                    value={incomeCents}
                                    onCentsChange={setIncomeCents}
                                    currencySymbol={activeCurrencySymbol}
                                />
                            </div>
                        </div>

                        {/* Fixed Deductions & Commitments (Optional) */}
                        <div className="space-y-3 border-t pt-4">
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
                                    onClick={handleAddDeduction}
                                    className="gap-1 h-8 text-xs"
                                >
                                    <PlusIcon className="size-3.5" />
                                    Add Deduction
                                </Button>
                            </div>

                            {deductions.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-3 text-center text-xs text-muted-foreground">
                                    No fixed deductions added yet. Click <strong>+ Add Deduction</strong> if you have monthly commitments.
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {deductions.map((deduction, idx) => (
                                        <div key={idx} className="flex items-center gap-2">
                                            <input
                                                type="text"
                                                placeholder="e.g. Health Insurance, Taxes"
                                                value={deduction.description}
                                                onChange={(e) => handleUpdateDeduction(idx, 'description', e.target.value)}
                                                className="h-9 flex-1 rounded-lg border border-input bg-transparent px-3 text-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            />
                                            <div className="w-36">
                                                <CurrencyInput
                                                    value={deduction.amount}
                                                    onCentsChange={(val) => handleUpdateDeduction(idx, 'amount', val)}
                                                    currencySymbol={activeCurrencySymbol}
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleRemoveDeduction(idx)}
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
                        <div className="rounded-lg border bg-muted/30 p-3 text-xs flex items-center justify-between text-muted-foreground">
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
                    </div>
                )}

                {/* STEP 3: Payment Account (Updating auto-created personal account) */}
                {currentStep === 3 && (
                    <div className="space-y-4">
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                <WalletIcon className="size-5 text-primary" />
                                Personal Account Setup
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Update the name and starting balance for your primary payment account.
                            </p>
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">
                                Account Name
                            </label>
                            <input
                                type="text"
                                placeholder="e.g. Personal Checking, Revolut"
                                value={accountName}
                                onChange={(e) => setAccountName(e.target.value)}
                                className="h-10 w-full rounded-lg border border-input bg-transparent px-3 py-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                        </div>

                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">
                                Initial Balance ({activeCurrencySymbol})
                            </label>
                            <CurrencyInput
                                value={accountBalanceCents}
                                onCentsChange={setAccountBalanceCents}
                                currencySymbol={activeCurrencySymbol}
                            />
                        </div>

                        {accountMutation.isError && (
                            <p className="text-xs text-destructive">
                                {getErrorMessage(accountMutation.error)}
                            </p>
                        )}
                    </div>
                )}

                {/* STEP 4: All Set */}
                {currentStep === 4 && (
                    <div className="space-y-4 text-center py-4">
                        <div className="size-12 rounded-full bg-emerald-500/15 text-emerald-600 mx-auto flex items-center justify-center">
                            <CheckCircle2Icon className="size-7" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="text-xl font-bold tracking-tight text-foreground">
                                You're All Set!
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Your space <strong>"{createdLedger?.name || spaceName || 'Our Household'}"</strong> is ready to use.
                            </p>
                        </div>

                        <div className="rounded-xl border bg-muted/20 p-4 text-xs space-y-2 text-left max-w-md mx-auto font-mono">
                            <div className="flex justify-between border-b pb-1">
                                <span className="text-muted-foreground">Space:</span>
                                <span className="font-semibold">{createdLedger?.name}</span>
                            </div>
                            <div className="flex justify-between border-b pb-1">
                                <span className="text-muted-foreground">Currency:</span>
                                <span className="font-semibold">{currencyCode} ({activeCurrencySymbol})</span>
                            </div>
                            <div className="flex justify-between border-b pb-1">
                                <span className="text-muted-foreground">Gross Income:</span>
                                <span className="font-semibold text-emerald-600">{centsToCurrency(incomeCents, activeCurrencySymbol)}</span>
                            </div>
                            {totalDeductionsCents > 0 && (
                                <div className="flex justify-between border-b pb-1">
                                    <span className="text-muted-foreground">Fixed Deductions:</span>
                                    <span className="font-semibold text-rose-500">-{centsToCurrency(totalDeductionsCents, activeCurrencySymbol)}</span>
                                </div>
                            )}
                            <div className="flex justify-between border-b pb-1">
                                <span className="text-muted-foreground">Net Shareable Capacity:</span>
                                <span className="font-semibold text-foreground">{centsToCurrency(netCapacityCents, activeCurrencySymbol)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Payment Account:</span>
                                <span className="font-semibold">{accountName}</span>
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>

            <CardFooter className="flex items-center justify-between border-t bg-muted/10 pt-4">
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
                        onClick={() => createSpaceMutation.mutate()}
                        disabled={createSpaceMutation.isPending || !spaceName.trim()}
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
                        onClick={() => profileMutation.mutate()}
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
                        onClick={() => accountMutation.mutate()}
                        disabled={accountMutation.isPending || !accountName.trim()}
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
