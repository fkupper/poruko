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
}): Promise<Ledger> {
    const { data } = await client.post<{ data: Ledger }>('/ledgers', payload);
    return data.data;
}
