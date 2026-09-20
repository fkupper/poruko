import * as React from 'react';
import type { Account } from '@/api/types';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { Field, FieldLabel } from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface AccountSelectorProps {
    accounts: Account[] | undefined;
    value: number | null;
    onChange: (id: number) => void;
    label: string;
    usage: 'payer' | 'destination';
    id?: string;
    autoSelect?: boolean;
}

export function AccountSelector({
    accounts,
    value,
    onChange,
    label,
    usage,
    id,
    autoSelect = true,
}: AccountSelectorProps) {
    const currencySymbol = useLedgerCurrencySymbol();
    const fieldId = id ?? `account-${usage}`;

    const filteredAccounts = React.useMemo(() => {
        if (!accounts) return [];
        if (usage === 'payer') {
            return accounts.filter((a) => a.type !== 'space_expense');
        }
        if (usage === 'destination') {
            return accounts.filter((a) => a.type === 'space_expense');
        }
        return accounts;
    }, [accounts, usage]);

    React.useEffect(() => {
        if (!autoSelect) return;
        if (filteredAccounts.length > 0 && value === null) {
            onChange(filteredAccounts[0].id);
        }
    }, [autoSelect, filteredAccounts, value, onChange]);

    return (
        <Field>
            <FieldLabel htmlFor={fieldId}>{label}</FieldLabel>
            {filteredAccounts.length > 0 ? (
                <Select value={String(value ?? '')} onValueChange={(val) => onChange(Number(val))}>
                    <SelectTrigger id={fieldId} className="w-full">
                        <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {filteredAccounts.map((acc) => (
                                <SelectItem key={acc.id} value={String(acc.id)}>
                                    {acc.name}{' '}
                                    {usage === 'payer' ? `(${centsToCurrency(acc.balance, currencySymbol)})` : ''}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            ) : (
                <p className="rounded-lg border border-dashed border-border px-2.5 py-1.5 text-sm text-muted-foreground">
                    No accounts available
                </p>
            )}
        </Field>
    );
}
