import { keepPreviousData, queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { request } from './client';
import type { ApiResponse, LedgerCycleConfig, SettlementPreviewSummary, SettlementStatusItem } from './types';

export function settlementPreviewQueryOptions(ledgerId: number, date: string) {
  return queryOptions({
    queryKey: ['settlements', 'preview', ledgerId, date] as const,
    queryFn: async () => {
      const response = await request<ApiResponse<SettlementPreviewSummary>>(
        `/api/ledgers/${ledgerId}/settlements/preview?date=${encodeURIComponent(date)}`,
      );
      return response.data;
    },
    placeholderData: keepPreviousData,
  });
}

export function useSettlementPreviewQuery(ledgerId: number, date: string) {
  return useQuery(settlementPreviewQueryOptions(ledgerId, date));
}

export function settlementsStatusQueryOptions(ledgerId: number) {
  return queryOptions({
    queryKey: ['settlements', 'status', ledgerId] as const,
    queryFn: async () => {
      const response = await request<ApiResponse<SettlementStatusItem[]>>(`/api/ledgers/${ledgerId}/settlements`);
      return response.data;
    },
    placeholderData: keepPreviousData,
  });
}

export function useSettlementsStatusQuery(ledgerId: number) {
  return useQuery(settlementsStatusQueryOptions(ledgerId));
}

export function useUpdateCycleConfigMutation(ledgerId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['ledgerCycleConfig', ledgerId],
    mutationFn: async (payload: LedgerCycleConfig) => {
      const response = await request<ApiResponse<LedgerCycleConfig>>(
        `/api/ledgers/${ledgerId}/cycle-config`,
        {
          method: 'PATCH',
          body: payload,
        },
      );
      return response.data;
    },
    onSuccess: () => {
      return Promise.all([
        queryClient.invalidateQueries({ queryKey: ['ledgerCycleConfig', ledgerId] }),
        queryClient.invalidateQueries({ queryKey: ['ledgers'] }),
      ]);
    },
  });
}

export function useConfirmSettlementCycleMutation(ledgerId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['confirmSettlementCycle', ledgerId],
    mutationFn: async (cycleIdentifier: string) => {
      const response = await request<ApiResponse<SettlementStatusItem>>(
        `/api/ledgers/${ledgerId}/settlements/${encodeURIComponent(cycleIdentifier)}/confirm`,
        { method: 'POST' },
      );
      return response.data;
    },
    onSuccess: () => {
      return Promise.all([
        queryClient.invalidateQueries({ queryKey: ['settlements', 'status', ledgerId] }),
        queryClient.invalidateQueries({ queryKey: ['settlements', 'preview', ledgerId] }),
        queryClient.invalidateQueries({ queryKey: ['transactions', ledgerId] }),
        queryClient.invalidateQueries({ queryKey: ['accounts', ledgerId] }),
      ]);
    },
  });
}

