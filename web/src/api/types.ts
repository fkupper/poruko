export interface User {
    id: number;
    name: string;
    email: string;
}

export interface AuthResponse {
    user: User;
    token: string;
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
}

export interface Ledger {
    id: number;
    name: string;
    settlement_mode: 'direct_p2p' | 'joint_clearinghouse';
    settlement_timezone: string;
    settlement_cutoff_day: number;
    settlement_cutoff_time: string;
    settlement_auto_execute_enabled: boolean;
    users_count?: number;
    created_at: string | null;
    updated_at: string | null;
}

export type SplitRule = 'proportional' | 'equal' | 'individual';
export type TransactionType = 'manual' | 'recurring' | 'settlement' | 'reversal';

export interface ParticipantShare {
    user_id: number;
    share_amount?: number;
    share_ratio?: number;
}

export interface Transaction {
    id: number;
    ledger_id: number;
    payer_account_id?: number;
    amount: number;
    description: string;
    date: string;
    type: TransactionType;
    split_rule: SplitRule;
    participants?: ParticipantShare[];
    created_at: string;
}

export interface CreateTransactionPayload {
    payer_account_id: number;
    amount: number;
    description: string;
    date: string;
    type: TransactionType;
    split_rule: SplitRule;
    participants?: ParticipantShare[];
}

export interface LedgerMember {
    id: number;
    name: string;
    shareable_income: number;
}

export interface IncomeOrDeductionItem {
    description: string;
    amount: number;
}

export interface FinancialProfile {
    id: number;
    ledger_id: number;
    user_id: number;
    valid_from: string;
    valid_to: string | null;
    incomes: IncomeOrDeductionItem[];
    deductions: IncomeOrDeductionItem[];
    computed: {
        total_income: number;
        total_deductions: number;
        shareable_income: number;
    };
}

export interface RecurringBlueprint {
    id: number;
    payer_account_id: number;
    amount: number;
    description: string;
    split_rule: SplitRule;
    participants?: ParticipantShare[];
    frequency: 'weekly' | 'monthly' | 'annual';
    valid_from: string;
    valid_to: string | null;
    is_active: boolean;
}

export interface CreateRecurringPayload {
    payer_account_id: number;
    amount: number;
    description: string;
    split_rule: SplitRule;
    participants?: ParticipantShare[];
    start_date: string;
    frequency: 'weekly' | 'monthly' | 'annual';
}

export interface SettlementSummary {
    total_shared_spend: number;
    pool_base_budget: number;
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

export interface SettlementPreview {
    period_start: string;
    period_end: string;
    settlement_mode: string;
    summary: SettlementSummary;
    user_breakdowns: SettlementUserBreakdown[];
    required_transfers: SettlementTransferInstruction[];
}

export interface PendingTransaction {
    id: number;
    date: string;
    raw_description: string;
    suggested_description: string;
    suggested_amount: number;
    suggested_split_rule: SplitRule;
    suggested_participants?: ParticipantShare[];
    status: 'pending' | 'approved' | 'rejected';
    confidence?: number;
    rationale?: string;
}

export interface ApprovePendingItem {
    pending_transaction_id: number;
    description?: string;
    amount?: number;
    split_rule?: SplitRule;
    participants?: ParticipantShare[];
}

export interface Account {
    id: number;
    ledger_id: number;
    name: string;
    type: 'joint_pool' | 'personal' | 'cash' | 'credit';
    balance: number;
}
