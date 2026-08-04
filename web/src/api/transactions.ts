import client from '@/api/client';
import type { CreateTransactionPayload, Transaction } from '@/api/types';

export async function fetchTransactions(ledgerId: number): Promise<Transaction[]> {
    const { data } = await client.get<{ data: Transaction[] }>(`/ledgers/${ledgerId}/transactions`);
    return data.data;
}

export async function createTransaction(ledgerId: number, payload: CreateTransactionPayload): Promise<Transaction> {
    const { data } = await client.post<{ data: Transaction }>(`/ledgers/${ledgerId}/transactions`, payload);
    return data.data;
}
