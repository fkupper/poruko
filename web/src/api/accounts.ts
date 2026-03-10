import {
  queryOptions,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { request } from './client';
import type { Account, ApiResponse, AccountType } from './types';

export interface CreateAccountInput {
  name: string;
  type: AccountType;
  owner_id?: number | null;
  code?: string | null;
}

export interface UpdateAccountInput extends Partial<CreateAccountInput> {
  id: number;
}

export function accountsQueryOptions(ledgerId: number) {
  return queryOptions({
    queryKey: ['accounts', ledgerId] as const,
    queryFn: async () => {
      const response = await request<ApiResponse<Account[]>>(
        `/api/ledgers/${ledgerId}/accounts`,
      );
      return response.data;
    },
  });
}

export function useAccountsQuery(ledgerId: number) {
  return useQuery(accountsQueryOptions(ledgerId));
}

export function useCreateAccountMutation(ledgerId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: CreateAccountInput) => {
      const response = await request<ApiResponse<Account>>(
        `/api/ledgers/${ledgerId}/accounts`,
        {
          method: 'POST',
          body: payload,
        },
      );
      return response.data;
    },
    onSuccess: () => {
      return queryClient.invalidateQueries({ queryKey: ['accounts', ledgerId] });
    },
  });
}

export function useUpdateAccountMutation(ledgerId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, ...payload }: UpdateAccountInput) => {
      const response = await request<ApiResponse<Account>>(
        `/api/ledgers/${ledgerId}/accounts/${id}`,
        {
          method: 'PATCH',
          body: payload,
        },
      );
      return response.data;
    },
    onSuccess: () => {
      return queryClient.invalidateQueries({ queryKey: ['accounts', ledgerId] });
    },
  });
}

