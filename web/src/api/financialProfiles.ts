import {
  queryOptions,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { request } from './client';
import type { ApiResponse, FinancialLineItem, FinancialProfile } from './types';

export interface UpdateFinancialProfileInput {
  incomes: FinancialLineItem[];
  deductions: FinancialLineItem[];
}

export function financialProfileQueryOptions(ledgerId: number, userId: number) {
  return queryOptions({
    queryKey: ['financialProfile', ledgerId, userId] as const,
    queryFn: async () => {
      const response = await request<ApiResponse<FinancialProfile>>(
        `/api/ledgers/${ledgerId}/users/${userId}/financial-profile/active`,
      );
      return response.data;
    },
    retry: (failureCount, error) => {
      if (error instanceof Error && error.message.includes('status 404')) {
        return false;
      }
      return failureCount < 3;
    },
  });
}

export function useFinancialProfileQuery(ledgerId: number, userId: number) {
  return useQuery(financialProfileQueryOptions(ledgerId, userId));
}

export function useUpdateFinancialProfileMutation(ledgerId: number, userId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['updateFinancialProfile', ledgerId, userId],
    mutationFn: async (payload: UpdateFinancialProfileInput) => {
      const response = await request<ApiResponse<FinancialProfile>>(
        `/api/ledgers/${ledgerId}/users/${userId}/financial-profile/active`,
        {
          method: 'PUT',
          body: payload,
        },
      );
      return response.data;
    },
    onSuccess: () => {
      return queryClient.invalidateQueries({
        queryKey: ['financialProfile', ledgerId, userId],
      });
    },
  });
}
