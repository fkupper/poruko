export type SplitRule = 'equal' | 'individual';
export type TransactionType = 'manual' | 'recurring' | 'settlement' | 'reversal';
export type AccountType = 'personal' | 'pool' | 'external';
export type PostingDirection = 'debit' | 'credit';

export interface ApiResponse<T> {
  data: T;
}

export interface Account {
  id: number;
  ledger_id: number;
  owner_id: number | null;
  type: AccountType;
  name: string;
  code: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface Posting {
  id: number;
  transaction_id: number;
  account_id: number;
  direction: PostingDirection;
  amount: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface Transaction {
  id: number;
  ledger_id: number;
  payer_account_id: number;
  amount: number;
  type: TransactionType;
  split_rule: SplitRule;
  participants: Array<{ account_id: number; amount?: number }>;
  description: string | null;
  date: string;
  postings?: Posting[];
  created_at: string | null;
  updated_at: string | null;
}

