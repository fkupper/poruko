import client from '@/api/client';
import type { CreateRecurringPayload, RecurringBlueprint } from '@/api/types';

export async function fetchRecurringBlueprints(ledgerId: number): Promise<RecurringBlueprint[]> {
    const { data } = await client.get<{ data: RecurringBlueprint[] }>(`/ledgers/${ledgerId}/recurring-transactions`);
    return data.data;
}

export async function createRecurringBlueprint(
    ledgerId: number,
    payload: CreateRecurringPayload
): Promise<RecurringBlueprint> {
    const { data } = await client.post<{ data: RecurringBlueprint }>(`/ledgers/${ledgerId}/recurring-transactions`, payload);
    return data.data;
}
