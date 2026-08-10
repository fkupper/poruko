import { useQuery } from '@tanstack/react-query';

import { fetchLedgers } from '@/api/ledgers';
import { useLedgerStore } from '@/stores/ledgerStore';

const FALLBACK_SYMBOL = '€';

/**
 * Returns the active ledger's currency symbol, falling back to € when unknown.
 */
export function useLedgerCurrencySymbol(): string {
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    const { data: ledgers } = useQuery({
        queryKey: ['ledgers'],
        queryFn: fetchLedgers,
    });

    const activeLedger = ledgers?.find((l) => l.id === activeLedgerId) ?? ledgers?.[0];
    return activeLedger?.currency_symbol ?? FALLBACK_SYMBOL;
}
