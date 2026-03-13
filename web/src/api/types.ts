export type SplitRule = 'equal' | 'individual' | 'proportional';
export type TransactionType = 'manual' | 'recurring' | 'settlement' | 'reversal';
export type AccountType = 'personal' | 'pool' | 'external';
export type PostingDirection = 'debit' | 'credit';

export interface FinancialLineItem {
  description: string;
  amount: number;
}

export interface FinancialProfileComputed {
  total_income: number;
  total_deductions: number;
  shareable_income: number;
}

export interface FinancialProfile {
  id: number;
  ledger_id: number;
  user_id: number;
  valid_from: string;
  valid_to: string | null;
  incomes: FinancialLineItem[];
  deductions: FinancialLineItem[];
  computed: FinancialProfileComputed;
}

export interface ApiResponse<T> {
  data: T;
}

export type SettlementMode = 'joint_clearinghouse' | 'direct_p2p';

export interface Ledger {
  id: number;
  name: string;
  settlement_mode: SettlementMode;
  pool_base_budget: number;
  users_count?: number;
  created_at: string | null;
  updated_at: string | null;
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

