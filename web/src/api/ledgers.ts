import client from '@/api/client';
import type { Ledger } from '@/api/types';

export async function fetchLedgers(): Promise<Ledger[]> {
    const { data } = await client.get<{ data: Ledger[] }>('/ledgers');
    return data.data;
}

export async function createLedger(payload: {
    name: string;
    currency?: string;
    settlement_mode?: 'direct_p2p' | 'joint_clearinghouse';
    settlement_cutoff_day?: number;
    settlement_timezone?: string;
    settlement_auto_execute_enabled?: boolean;
}): Promise<Ledger> {
    const { data } = await client.post<{ data: Ledger }>('/ledgers', payload);
    return data.data;
}

export async function updateLedgerSettings(
    ledgerId: number,
    payload: {
        name?: string;
        currency_code?: string;
        settlement_cutoff_day?: number;
        settlement_timezone?: string;
        settlement_cutoff_time?: string;
        settlement_auto_execute_enabled?: boolean;
    }
): Promise<Ledger> {
    const { data } = await client.put<{ data: Ledger }>(`/ledgers/${ledgerId}/settings`, payload);
    return data.data;
}

export async function updateMyPreferences(
    ledgerId: number,
    payload: {
        default_payment_account_id?: number | null;
        default_expense_account_id?: number | null;
    }
): Promise<Ledger> {
    const { data } = await client.put<{ data: Ledger }>(`/ledgers/${ledgerId}/my-preferences`, payload);
    return data.data;
}
