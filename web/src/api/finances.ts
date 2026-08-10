import client from '@/api/client';
import type { FinancialProfile, IncomeOrDeductionItem } from '@/api/types';

export async function fetchFinancialProfile(
    ledgerId: number,
    userId: number
): Promise<FinancialProfile | null> {
    try {
        const { data } = await client.get<{ data: FinancialProfile }>(
            `/ledgers/${ledgerId}/users/${userId}/financial-profile/active`
        );
        return data.data;
    } catch (err) {
        const status = (err as { response?: { status?: number } }).response?.status;
        if (status === 404) {
            return null;
        }
        throw err;
    }
}

export async function updateFinancialProfile(
    ledgerId: number,
    userId: number,
    payload: { incomes: IncomeOrDeductionItem[]; deductions: IncomeOrDeductionItem[] }
): Promise<FinancialProfile> {
    const { data } = await client.put<{ data: FinancialProfile }>(
        `/ledgers/${ledgerId}/users/${userId}/financial-profile/active`,
        payload
    );
    return data.data;
}
