import client from '@/api/client';
import type { FinancialProfile, IncomeOrDeductionItem } from '@/api/types';

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
