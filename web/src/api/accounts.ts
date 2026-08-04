import client from '@/api/client';
import type { Account } from '@/api/types';

export async function fetchAccounts(ledgerId: number): Promise<Account[]> {
    const { data } = await client.get<{ data: Account[] }>(`/ledgers/${ledgerId}/accounts`);
    return data.data;
}

export async function createAccount(
    ledgerId: number,
    payload: { name: string; type: Account['type']; balance?: number }
): Promise<Account> {
    const { data } = await client.post<{ data: Account }>(`/ledgers/${ledgerId}/accounts`, payload);
    return data.data;
}
