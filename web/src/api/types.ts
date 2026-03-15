export type SplitRule = 'equal' | 'individual' | 'proportional' | 'manual';
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

export interface SettlementSummary {
  total_shared_spend: number;
  pool_current_balance: number;
}

export interface SettlementUserBreakdown {
  user_id: number;
  name: string;
  active_ratio: number;
  target_liability: number;
  paid_out_of_pocket: number;
  net_balance: number;
}

export interface SettlementTransferInstruction {
  from_account_id: number;
  to_account_id: number;
  amount: number;
  instruction: string;
}

export interface SettlementSafetyGate {
  auto_allowed: boolean;
  reason: string | null;
}

export interface SettlementPreviewSummary {
  ledger_id: number;
  period_start: string;
  period_end: string;
  settlement_mode: SettlementMode;
  summary: SettlementSummary;
  user_breakdowns: SettlementUserBreakdown[];
  required_transfers: SettlementTransferInstruction[];
  safety_gate?: SettlementSafetyGate;
}

export interface SettlementStatusItem {
  id: number;
  ledger_id: number;
  period_start: string;
  period_end: string;
  executed_at: string | null;
  confirmation_required?: boolean;
  transactions?: Transaction[];
}

export interface LedgerCycleConfig {
  settlement_timezone: string;
  settlement_cutoff_day: number;
  settlement_cutoff_time: string;
  settlement_auto_execute_enabled: boolean;
}

export interface Ledger {
  id: number;
  name: string;
  settlement_mode: SettlementMode;
  settlement_timezone?: string | null;
  settlement_cutoff_day?: number | null;
  settlement_cutoff_time?: string | null;
  settlement_auto_execute_enabled?: boolean | null;
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
  base_budget: number;
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
  credit_account_id: number;
  credit_account_name?: string | null;
  debit_account_id: number;
  debit_account_name?: string | null;
  amount: number;
  type: TransactionType;
  split_rule: SplitRule;
  participants: Array<{ user_id: number; share?: number }> | null;
  description: string | null;
  date: string;
  postings?: Posting[];
  created_at: string | null;
  updated_at: string | null;
}

