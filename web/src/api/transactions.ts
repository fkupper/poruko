import {
  keepPreviousData,
  queryOptions,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { request } from './client';
import type { ApiResponse, SplitRule, Transaction } from './types';

export interface TransactionFilters {
  from_date?: string;
  to_date?: string;
  account_id?: number;
}

export interface CreateTransactionParticipant {
  user_id: number;
  share?: number;
}

export interface CreateTransactionInput {
  credit_account_id: number;
  debit_account_id: number;
  amount: number;
  description?: string;
  date: string;
  split_rule: SplitRule;
  participants?: CreateTransactionParticipant[] | null;
  type?: 'manual';
}

function toQueryString(filters: TransactionFilters): string {
  const search = new URLSearchParams();

  if (filters.from_date !== undefined) {
    search.set('from_date', filters.from_date);
  }

  if (filters.to_date !== undefined) {
    search.set('to_date', filters.to_date);
  }

  if (filters.account_id !== undefined) {
    search.set('account_id', String(filters.account_id));
  }

  const serialized = search.toString();

  return serialized.length > 0 ? `?${serialized}` : '';
}

export function transactionsQueryOptions(ledgerId: number, filters: TransactionFilters = {}) {
  return queryOptions({
    queryKey: ['transactions', ledgerId, filters] as const,
    queryFn: async () => {
      const response = await request<ApiResponse<Transaction[]>>(
        `/api/ledgers/${ledgerId}/transactions${toQueryString(filters)}`,
      );
      return response.data;
    },
    placeholderData: keepPreviousData,
  });
}

export function useTransactionsQuery(ledgerId: number, filters: TransactionFilters = {}) {
  return useQuery(transactionsQueryOptions(ledgerId, filters));
}

export function useCreateTransactionMutation(ledgerId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['createTransaction', ledgerId],
    mutationFn: async (payload: CreateTransactionInput) => {
      const response = await request<ApiResponse<Transaction>>(
        `/api/ledgers/${ledgerId}/transactions`,
        {
          method: 'POST',
          body: payload,
        },
      );
      return response.data;
    },
    onSuccess: () => {
      return Promise.all([
        queryClient.invalidateQueries({ queryKey: ['transactions', ledgerId] }),
        queryClient.invalidateQueries({ queryKey: ['accounts', ledgerId] }),
      ]);
    },
  });
}

