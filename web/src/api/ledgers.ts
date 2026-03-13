import { keepPreviousData, queryOptions, useQuery } from '@tanstack/react-query';
import { request } from './client';
import type { ApiResponse, Ledger } from './types';

export function ledgersQueryOptions() {
  return queryOptions({
    queryKey: ['ledgers'] as const,
    queryFn: async () => {
      const response = await request<ApiResponse<Ledger[]>>('/api/ledgers');
      return response.data;
    },
    placeholderData: keepPreviousData,
  });
}

export function useLedgersQuery(enabled: boolean) {
  return useQuery({
    ...ledgersQueryOptions(),
    enabled,
  });
}

