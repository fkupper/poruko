import client from '@/api/client';
import type { Ledger } from '@/api/types';

export async function fetchLedgers(): Promise<Ledger[]> {
    const { data } = await client.get<{ data: Ledger[] }>('/ledgers');
    return data.data;
}
