import client from '@/api/client';
import type { LedgerMember } from '@/api/types';

export async function fetchLedgerMembers(ledgerId: number, date?: string): Promise<LedgerMember[]> {
    const params = date ? { date } : {};
    const { data } = await client.get<{ data: LedgerMember[] }>(`/ledgers/${ledgerId}/users`, { params });
    return data.data;
}
