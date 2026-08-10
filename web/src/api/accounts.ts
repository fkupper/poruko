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

export async function updateAccount(
    ledgerId: number,
    accountId: number,
    payload: { name?: string; current_funds?: number }
): Promise<Account> {
    const { data } = await client.patch<{ data: Account }>(
        `/ledgers/${ledgerId}/accounts/${accountId}`,
        payload
    );
    return data.data;
}

export async function deleteAccount(
    ledgerId: number,
    accountId: number
): Promise<void> {
    await client.delete(`/ledgers/${ledgerId}/accounts/${accountId}`);
}
