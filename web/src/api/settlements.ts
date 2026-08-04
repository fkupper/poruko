import client from '@/api/client';
import type { SettlementPreview } from '@/api/types';

export async function fetchSettlementPreview(ledgerId: number, date?: string): Promise<SettlementPreview> {
    const params = date ? { date } : {};
    const { data } = await client.get<{ data: SettlementPreview }>(`/ledgers/${ledgerId}/settlements/preview`, { params });
    return data.data;
}

export async function executeSettlement(ledgerId: number, periodEnd: string): Promise<void> {
    await client.post(`/ledgers/${ledgerId}/settlements`, { period_end: periodEnd });
}
