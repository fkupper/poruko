import client from '@/api/client';
import type { LedgerMember } from '@/api/types';

export async function fetchLedgerMembers(ledgerId: number, date?: string): Promise<LedgerMember[]> {
    const params = date ? { date } : {};
    const { data } = await client.get<{ data: LedgerMember[] }>(`/ledgers/${ledgerId}/users`, { params });
    return data.data;
}

export async function createInvitation(ledgerId: number, expiresInDays: number = 7): Promise<{ token: string; expires_at: string }> {
    const { data } = await client.post<{ invitation: { token: string; expires_at: string } }>(`/ledgers/${ledgerId}/invitations`, { expires_in_days: expiresInDays });
    return data.invitation;
}

export async function resetTwoFactor(ledgerId: number, userId: number): Promise<void> {
    await client.delete(`/ledgers/${ledgerId}/users/${userId}/two-factor`);
}

export const deactivateMember = async (ledgerId: number, userId: number): Promise<void> => {
    await client.delete(`/ledgers/${ledgerId}/users/${userId}`);
};

export async function restoreMember(ledgerId: number, userId: number): Promise<void> {
    await client.post(`/ledgers/${ledgerId}/users/${userId}/restore`);
}
