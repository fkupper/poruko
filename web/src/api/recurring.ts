import client from '@/api/client';
import type { CreateRecurringPayload, RecurringBlueprint } from '@/api/types';

export async function fetchRecurringBlueprints(ledgerId: number, status = 'all'): Promise<RecurringBlueprint[]> {
    const { data } = await client.get<{ data: RecurringBlueprint[] }>(`/ledgers/${ledgerId}/recurring-transactions`, {
        params: { status }
    });
    return data.data;
}

export async function createRecurringBlueprint(
    ledgerId: number,
    payload: CreateRecurringPayload
): Promise<RecurringBlueprint> {
    const { data } = await client.post<{ data: RecurringBlueprint }>(`/ledgers/${ledgerId}/recurring-transactions`, payload);
    return data.data;
}

export async function updateRecurringBlueprint(
    ledgerId: number,
    blueprintId: number,
    payload: Partial<CreateRecurringPayload>
): Promise<RecurringBlueprint> {
    const { data } = await client.patch<{ data: RecurringBlueprint }>(`/ledgers/${ledgerId}/recurring-transactions/${blueprintId}`, payload);
    return data.data;
}

export async function deleteRecurringBlueprint(
    ledgerId: number,
    blueprintId: number
): Promise<void> {
    await client.delete(`/ledgers/${ledgerId}/recurring-transactions/${blueprintId}`);
}
