import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useForm, Controller, type UseFormSetError } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import axios from 'axios';
import { updateLedgerSettings, updateMyPreferences, fetchLedgers } from '@/api/ledgers';
import { fetchCurrencies } from '@/api/currencies';
import { fetchAccounts } from '@/api/accounts';
import type { ApiError } from '@/api/types';
import { useLedgerStore } from '@/stores/ledgerStore';
import { useAuthStore } from '@/stores/authStore';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Loader2Icon,
    SettingsIcon,
    AlertTriangleIcon,
    GlobeIcon,
    SaveIcon,
    CheckCircle2Icon,
    UserCircleIcon,
} from 'lucide-react';
import { McpOperationSettings } from '@/features/mcp/McpOperationSettings/McpOperationSettings';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldLabel, FieldError, FieldGroup, FieldContent } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

const automationSchema = z.object({
    name: z.string().min(1, 'Space name is required'),
    currency_code: z.string().length(3, 'Currency is required'),
    settlement_timezone: z.string().min(1, 'Timezone is required'),
    settlement_cutoff_day: z.number().int().min(1).max(31),
    settlement_auto_execute_enabled: z.boolean(),
    ack: z.boolean().optional(),
    default_payment_account_id: z.number().nullable().optional(),
    default_expense_account_id: z.number().nullable().optional(),
}).refine((data) => !data.settlement_auto_execute_enabled || data.ack, {
    message: 'You must acknowledge the risks to enable Auto-Approve & Execute',
    path: ['ack'],
});

type AutomationFormValues = z.infer<typeof automationSchema>;

const FORM_API_FIELDS = [
    'name',
    'currency_code',
    'settlement_timezone',
    'settlement_cutoff_day',
    'settlement_auto_execute_enabled',
    'default_payment_account_id',
    'default_expense_account_id',
] as const satisfies ReadonlyArray<keyof AutomationFormValues>;

function applyApiErrors(error: unknown, setError: UseFormSetError<AutomationFormValues>): void {
    if (!axios.isAxiosError<ApiError>(error)) {
        setError('root', { message: 'Failed to save settings. Please try again.' });
        return;
    }

    const data = error.response?.data;
    const fieldErrors = data?.errors ?? {};
    let mappedField = false;

    for (const [field, messages] of Object.entries(fieldErrors)) {
        const message = messages[0];
        if (!message) continue;

        if ((FORM_API_FIELDS as readonly string[]).includes(field)) {
            setError(field as (typeof FORM_API_FIELDS)[number], { message });
            mappedField = true;
        }
    }

    setError('root', {
        message: data?.message
            ?? (mappedField ? 'Please fix the highlighted fields and try again.' : 'Failed to save settings. Please try again.'),
    });
}

export default function SettingsPage() {
    const [saveSuccess, setSaveSuccess] = React.useState(false);
    const [isFormHydrated, setIsFormHydrated] = React.useState(false);
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const user = useAuthStore((s) => s.user);
    
    const { data: ledgers = [] } = useQuery({
        queryKey: ['ledgers'],
        queryFn: fetchLedgers,
    });

    const { data: accounts = [], isLoading: isLoadingAccounts } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    const activeLedger = ledgers.find(l => l.id === activeLedgerId) ?? ledgers[0];

    const { data: currenciesData } = useQuery({
        queryKey: ['currencies'],
        queryFn: fetchCurrencies,
    });
    const currencies = currenciesData?.available ?? [];

    const timezones = React.useMemo(() => {
        try {
            const tzs = Intl.supportedValuesOf('timeZone');
            return ['UTC', ...tzs.filter(tz => tz !== 'UTC')];
        } catch {
            return ['UTC'];
        }
    }, []);

    const form = useForm<AutomationFormValues>({
        resolver: zodResolver(automationSchema),
        defaultValues: {
            name: '',
            currency_code: '',
            settlement_timezone: 'UTC',
            settlement_cutoff_day: 1, // Default to 1st of the month
            settlement_auto_execute_enabled: false,
            ack: false,
            default_payment_account_id: null,
            default_expense_account_id: null,
        },
    });
    const { setError, clearErrors, formState: { errors } } = form;

    React.useEffect(() => {
        if (activeLedger) {
            form.reset({
                name: activeLedger.name || '',
                currency_code: activeLedger.currency || currenciesData?.default || 'EUR',
                settlement_timezone: activeLedger.settlement_timezone || 'UTC',
                settlement_cutoff_day: activeLedger.settlement_cutoff_day || 1, // Fallback to 1st
                settlement_auto_execute_enabled: activeLedger.settlement_auto_execute_enabled || false,
                ack: activeLedger.settlement_auto_execute_enabled || false,
                default_payment_account_id: activeLedger.my_preferences?.default_payment_account_id ?? null,
                default_expense_account_id: activeLedger.my_preferences?.default_expense_account_id ?? null,
            });
            setIsFormHydrated(true);
        }
    }, [activeLedger, currenciesData, form]);

    const settingsMutation = useMutation({
        mutationFn: async (values: AutomationFormValues) => {
            const targetLedgerId = activeLedgerId ?? activeLedger?.id;
            if (!targetLedgerId) {
                throw new Error('No active space selected.');
            }

            await updateMyPreferences(targetLedgerId, {
                default_payment_account_id: values.default_payment_account_id,
                default_expense_account_id: values.default_expense_account_id,
            });

            return updateLedgerSettings(targetLedgerId, {
                name: values.name,
                currency_code: values.currency_code,
                settlement_timezone: values.settlement_timezone,
                settlement_cutoff_day: values.settlement_cutoff_day,
                settlement_auto_execute_enabled: values.settlement_auto_execute_enabled,
            });
        },
        onSuccess: () => {
            clearErrors('root');
            queryClient.invalidateQueries({ queryKey: ['ledgers'] });
            setSaveSuccess(true);
            setTimeout(() => setSaveSuccess(false), 3000);
        },
        onError: (error: unknown) => {
            setSaveSuccess(false);
            applyApiErrors(error, setError);
        },
    });

    const onSettingsSubmit = (values: AutomationFormValues) => {
        clearErrors();
        setSaveSuccess(false);
        settingsMutation.mutate(values);
    };

    return (
        <div className="flex flex-col gap-8 w-full">
        <form onSubmit={form.handleSubmit(onSettingsSubmit)} className="flex flex-col gap-6 w-full">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <SettingsIcon className="size-6 text-primary" />
                        Space Settings
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Configure your space preferences and automation settings.
                    </p>
                </div>
                <Button 
                    type="submit"
                    disabled={settingsMutation.isPending}
                    className="gap-2 shrink-0"
                >
                    {settingsMutation.isPending ? (
                        <Loader2Icon className="size-4 animate-spin" />
                    ) : saveSuccess ? (
                        <CheckCircle2Icon className="size-4" />
                    ) : (
                        <SaveIcon className="size-4" />
                    )}
                    {saveSuccess ? 'Saved!' : 'Save Settings'}
                </Button>
            </div>

            {errors.root && (
                <Alert variant="destructive">
                    <AlertTriangleIcon />
                    <AlertDescription>{errors.root.message}</AlertDescription>
                </Alert>
            )}

            {/* General Settings Card */}
            <Card className="bg-surface border-border">
                <CardHeader className="pb-3 border-b border-border mb-4">
                    <CardTitle className="text-lg font-bold text-primary flex items-center gap-2">
                        <GlobeIcon className="size-5 text-muted-foreground" />
                        General Settings
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-5">
                    <FieldGroup className="grid grid-cols-2 gap-4">
                        <Controller
                            control={form.control}
                            name="name"
                            render={({ field, fieldState }) => (
                                <Field>
                                    <FieldLabel>Space Name</FieldLabel>
                                    <FieldContent>
                                        <Input
                                            {...field}
                                            placeholder="Enter space name"
                                            className="h-10 w-full bg-background"
                                        />
                                    </FieldContent>
                                    <FieldError errors={[fieldState.error]} />
                                </Field>
                            )}
                        />
                        <Controller
                            control={form.control}
                            name="currency_code"
                            render={({ field, fieldState }) => (
                                <Field>
                                    <FieldLabel>Base Currency</FieldLabel>
                                    <FieldContent>
                                        {(isFormHydrated && currencies.length > 0) ? (
                                            <Select
                                                key={field.value || 'currency-empty'}
                                                value={field.value}
                                                onValueChange={field.onChange}
                                            >
                                                <SelectTrigger className="h-10 w-full bg-background">
                                                    <SelectValue placeholder="Select currency" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {currencies.map((currency) => (
                                                        <SelectItem key={currency.code} value={currency.code}>
                                                            {currency.code} - {currency.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <Select disabled>
                                                <SelectTrigger className="h-10 w-full bg-background">
                                                    <SelectValue placeholder="Loading..." />
                                                </SelectTrigger>
                                            </Select>
                                        )}
                                    </FieldContent>
                                    <FieldError errors={[fieldState.error]} />
                                </Field>
                            )}
                        />
                    </FieldGroup>
                </CardContent>
            </Card>

            {/* User Preferences Card */}
            <Card className="bg-surface border-border">
                <CardHeader className="pb-3 border-b border-border mb-4">
                    <CardTitle className="text-lg font-bold text-primary flex items-center gap-2">
                        <UserCircleIcon className="size-5 text-muted-foreground" />
                        My Default Accounts
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-5">
                    <p className="text-sm text-muted-foreground">
                        Set your preferred accounts to be pre-selected when logging transactions. These settings only apply to you.
                    </p>
                    <FieldGroup className="grid grid-cols-2 gap-4">
                        <Controller
                            control={form.control}
                            name="default_payment_account_id"
                            render={({ field, fieldState }) => (
                                <Field>
                                    <FieldLabel>Default Payment Account</FieldLabel>
                                    <FieldContent>
                                        {(() => {
                                            const paymentAccounts = accounts.filter(
                                                (a) =>
                                                    a.type === 'pool_asset'
                                                    || (a.type === 'user_funding' && a.owner_id === user?.id),
                                            );
                                            return (
                                                <Select
                                                    key={(!isLoadingAccounts && isFormHydrated) ? `payment-ready-${paymentAccounts.length}` : 'payment-loading'}
                                                    value={field.value ? String(field.value) : ""}
                                                    onValueChange={(v) => field.onChange(Number(v))}
                                                    disabled={isLoadingAccounts || !isFormHydrated}
                                                >
                                                    <SelectTrigger className="h-10 w-full bg-background">
                                                        <SelectValue placeholder={(!isLoadingAccounts && isFormHydrated) ? "Select payment account" : "Loading..."} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {paymentAccounts.map((acc) => (
                                                            <SelectItem key={acc.id} value={String(acc.id)}>
                                                                {acc.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            );
                                        })()}
                                    </FieldContent>
                                    <FieldError errors={[fieldState.error]} />
                                </Field>
                            )}
                        />
                        <Controller
                            control={form.control}
                            name="default_expense_account_id"
                            render={({ field, fieldState }) => (
                                <Field>
                                    <FieldLabel>Default Expense Category</FieldLabel>
                                    <FieldContent>
                                        {(() => {
                                            const expenseAccounts = accounts.filter(a => a.type === 'space_expense');
                                            return (
                                                <Select
                                                    key={(!isLoadingAccounts && isFormHydrated) ? `expense-ready-${expenseAccounts.length}` : 'expense-loading'}
                                                    value={field.value ? String(field.value) : ""}
                                                    onValueChange={(v) => field.onChange(Number(v))}
                                                    disabled={isLoadingAccounts || !isFormHydrated}
                                                >
                                                    <SelectTrigger className="h-10 w-full bg-background">
                                                        <SelectValue placeholder={(!isLoadingAccounts && isFormHydrated) ? "Select expense account" : "Loading..."} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {expenseAccounts.map((acc) => (
                                                            <SelectItem key={acc.id} value={String(acc.id)}>
                                                                {acc.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            );
                                        })()}
                                    </FieldContent>
                                    <FieldError errors={[fieldState.error]} />
                                </Field>
                            )}
                        />
                    </FieldGroup>
                </CardContent>
            </Card>

            {/* Automation Settings Card */}
            <Card className="bg-surface border-border">
                <CardHeader className="pb-3 border-b border-border mb-4">
                    <CardTitle className="text-lg font-bold text-primary flex items-center gap-2">
                        <SettingsIcon className="size-5 text-muted-foreground" />
                        Settlement Automation Settings
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-5">
                    <FieldGroup className="grid grid-cols-2 gap-4">
                            <Controller
                                control={form.control}
                                name="settlement_timezone"
                                render={({ field, fieldState }) => (
                                    <Field>
                                        <FieldLabel>Timezone</FieldLabel>
                                        <FieldContent>
                                            {isFormHydrated ? (
                                                <Select
                                                    key={field.value || 'tz-empty'}
                                                    value={field.value}
                                                    onValueChange={field.onChange}
                                                >
                                                    <SelectTrigger className="h-10 w-full bg-background">
                                                        <SelectValue placeholder="Select timezone" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {timezones.map((tz) => (
                                                            <SelectItem key={tz} value={tz}>
                                                                {tz}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            ) : (
                                                <Select disabled>
                                                    <SelectTrigger className="h-10 w-full bg-background">
                                                        <SelectValue placeholder="Loading..." />
                                                    </SelectTrigger>
                                                </Select>
                                            )}
                                        </FieldContent>
                                        <FieldError errors={[fieldState.error]} />
                                    </Field>
                                )}
                            />
                            <Controller
                                control={form.control}
                                name="settlement_cutoff_day"
                                render={({ field, fieldState }) => (
                                    <Field>
                                        <FieldLabel>Cutoff Day</FieldLabel>
                                        <FieldContent>
                                            {isFormHydrated ? (
                                                <Select
                                                    key={field.value ?? 'cutoff-empty'}
                                                    value={String(field.value)}
                                                    onValueChange={(val) => field.onChange(Number(val))}
                                                >
                                                    <SelectTrigger className="h-10 w-full bg-background">
                                                        <SelectValue placeholder="Select a day" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="1">1st of the month</SelectItem>
                                                        <SelectItem value="15">15th of the month</SelectItem>
                                                        <SelectItem value="31">Last day of the month</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            ) : (
                                                <Select disabled>
                                                    <SelectTrigger className="h-10 w-full bg-background">
                                                        <SelectValue placeholder="Loading..." />
                                                    </SelectTrigger>
                                                </Select>
                                            )}
                                        </FieldContent>
                                        <FieldError errors={[fieldState.error]} />
                                    </Field>
                                )}
                            />
                        </FieldGroup>

                        <div className="rounded-lg border bg-background p-4 space-y-3">
                            <Controller
                                control={form.control}
                                name="settlement_auto_execute_enabled"
                                render={({ field }) => (
                                    <Field orientation="horizontal" className="items-start gap-3 cursor-pointer">
                                        <Checkbox
                                            id="auto_execute"
                                            checked={field.value}
                                            onCheckedChange={field.onChange}
                                            disabled={!form.watch('ack') && !field.value}
                                            className="mt-1"
                                        />
                                        <label htmlFor="auto_execute" className="cursor-pointer">
                                            <div className="text-sm font-semibold text-primary flex items-center gap-2">
                                                Auto-Approve & Execute <AlertTriangleIcon className="size-4 text-amber-500" />
                                            </div>
                                            <div className="text-sm text-muted-foreground mt-1 leading-relaxed">
                                                Enabling this feature will automatically close the period at the cutoff time. 
                                                This carries an operational risk of falling out of sync with real-world accounts if mistakes occur.
                                            </div>
                                        </label>
                                    </Field>
                                )}
                            />
                            {!form.watch('settlement_auto_execute_enabled') && (
                                <Controller
                                    control={form.control}
                                    name="ack"
                                    render={({ field, fieldState }) => (
                                        <div className="ml-8 space-y-1">
                                            <Field orientation="horizontal" className="items-center gap-2 cursor-pointer">
                                                <Checkbox
                                                    id="ack_checkbox"
                                                    checked={field.value}
                                                    onCheckedChange={field.onChange}
                                                />
                                                <label htmlFor="ack_checkbox" className="text-xs font-medium text-primary cursor-pointer">
                                                    I understand and agree to the operational risks.
                                                </label>
                                            </Field>
                                            <FieldError errors={[fieldState.error]} />
                                        </div>
                                    )}
                                />
                            )}
                        </div>
                        
                </CardContent>
            </Card>
        </form>
        <McpOperationSettings />
        </div>
    );
}
