import client from '@/api/client';
import type { SettlementPeriod, SettlementPreview, Transaction } from '@/api/types';

export interface RecordMidCycleTransferPayload {
    period_end: string;
    from_account_id: number;
    to_account_id: number;
    amount: number;
    idempotency_key: string;
}

export async function fetchSettlementPreview(ledgerId: number, date?: string): Promise<SettlementPreview> {
    const params = date ? { date } : {};
    const { data } = await client.get<{ data: SettlementPreview }>(`/ledgers/${ledgerId}/settlements/preview`, { params });
    return data.data;
}

export async function fetchSettlementPeriods(ledgerId: number): Promise<SettlementPeriod[]> {
    const { data } = await client.get<{ data: SettlementPeriod[] }>(`/ledgers/${ledgerId}/settlements/periods`);
    return data.data;
}

export async function executeSettlement(ledgerId: number, periodEnd: string): Promise<void> {
    await client.post(`/ledgers/${ledgerId}/settlements/${periodEnd}/confirm`, { period_end: periodEnd });
}

export async function recordMidCycleSettlementTransfer(
    ledgerId: number,
    payload: RecordMidCycleTransferPayload,
): Promise<Transaction> {
    const { data } = await client.post<{ data: Transaction }>(
        `/ledgers/${ledgerId}/settlements/${payload.period_end}/transfers`,
        payload,
    );

    return data.data;
}
