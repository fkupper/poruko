import * as React from 'react';
import type { Account } from '@/api/types';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerCurrencySymbol } from '@/hooks/use-ledger-currency';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface AccountSelectorProps {
    accounts: Account[] | undefined;
    value: number | null;
    onChange: (id: number) => void;
    label: string;
    usage: 'payer' | 'destination';
}

export function AccountSelector({ accounts, value, onChange, label, usage }: AccountSelectorProps) {
    const currencySymbol = useLedgerCurrencySymbol();

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

    // Auto-select first account if not set
    React.useEffect(() => {
        if (filteredAccounts.length > 0 && value === null) {
            onChange(filteredAccounts[0].id);
        }
    }, [filteredAccounts, value, onChange]);

    return (
        <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1">
                {label}
            </label>
            {filteredAccounts.length > 0 ? (
                <Select value={String(value ?? '')} onValueChange={(val) => onChange(Number(val))}>
                    <SelectTrigger className="h-10 w-full bg-background">
                        <SelectValue placeholder="Select account" />
                    </SelectTrigger>
                    <SelectContent>
                        {filteredAccounts.map((acc) => (
                            <SelectItem key={acc.id} value={String(acc.id)}>
                                {acc.name}{' '}
                                {usage === 'payer' ? `(${centsToCurrency(acc.balance, currencySymbol)})` : ''}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : (
                <p className="text-xs text-muted-foreground rounded-lg border border-dashed border-border px-3 py-2.5">
                    No accounts available
                </p>
            )}
        </div>
    );
}
