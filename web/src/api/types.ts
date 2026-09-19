export interface User {
    id: number;
    name: string;
    email: string;
    theme: 'poruko' | 'neutral' | 'quiet' | 'neon-tokyo';
    color_mode: 'light' | 'dark' | 'system';
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
    currency?: string;
    currency_symbol?: string;
    settlement_mode: 'direct_p2p' | 'joint_clearinghouse';
    settlement_timezone: string;
    settlement_cutoff_day: number;
    settlement_cutoff_time: string;
    settlement_auto_execute_enabled: boolean;
    users_count?: number;
    created_at: string | null;
    updated_at: string | null;
    my_preferences?: {
        main_personal_account_id: number | null;
        default_payment_account_id: number | null;
        default_expense_account_id: number | null;
    };
}

export type SplitRule = 'proportional' | 'equal' | 'individual' | 'manual';
export type TransactionType = 'manual' | 'recurring' | 'settlement' | 'reversal';
export type TransactionSource = 'manual' | 'blueprint' | 'mcp' | 'ai_import' | 'system';

export interface ParticipantShare {
    user_id: number;
    share?: number;
    share_ratio?: number;
}

export interface Transaction {
    id: number;
    ledger_id: number;
    settlement_id: number | null;
    payer_account_id?: number;
    payer_account_name?: string;
    payer_account_owner_id?: number | null;
    destination_account_id?: number;
    destination_account_name?: string;
    amount: number;
    description: string;
    date: string;
    type: TransactionType;
    source: TransactionSource;
    source_metadata?: Record<string, unknown> | null;
    source_recurring_transaction_id?: number | null;
    split_rule: SplitRule;
    participants?: ParticipantShare[];
    postings?: Array<{
        id: number;
        account_id: number;
        account_name?: string;
        amount: number;
        direction: 'debit' | 'credit';
    }>;
    created_at: string;
    updated_at?: string;
}

export interface CreateTransactionPayload {
    payer_account_id: number;
    destination_account_id: number;
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
    email?: string;
    role?: string;
    shareable_income: number;
    is_active: boolean;
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
    series_id: string;
    payer_account_id: number;
    destination_account_id: number;
    amount: number;
    description: string;
    split_rule: SplitRule;
    participants?: ParticipantShare[];
    frequency: 'weekly' | 'monthly' | 'annual';
    valid_from: string;
    valid_to: string | null;
    is_active: boolean;
    status: 'active' | 'previous_version' | 'deleted';
}

export interface CreateRecurringPayload {
    payer_account_id: number;
    destination_account_id: number;
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

export interface SettlementPeriod {
    period_start: string;
    period_end: string;
    label: string;
    status: 'settled' | 'open' | 'future';
    executed_at: string | null;
}

export interface SettlementPreview {
    period_start: string;
    period_end: string;
    is_settled: boolean;
    executed_at: string | null;
    settlement_mode: string;
    summary: SettlementSummary;
    user_breakdowns: SettlementUserBreakdown[];
    required_transfers: SettlementTransferInstruction[];
}

export interface PendingTransaction {
    id: number;
    ledger_id: number;
    proposed_by_user_id: number;
    proposed_by_user_name?: string;
    payer_account_id: number | null;
    payer_account_name?: string | null;
    destination_account_id: number | null;
    destination_account_name?: string | null;
    raw_data: Record<string, unknown>;
    date: string | null;
    raw_description?: string | null;
    suggested_description: string | null;
    suggested_amount: number | null;
    suggested_split_rule: SplitRule | null;
    suggested_participants?: ParticipantShare[];
    source: TransactionSource;
    status: 'pending' | 'approved' | 'rejected';
    confidence?: number;
    rationale?: string;
    reviewed_by_user_id?: number | null;
    reviewed_at?: string | null;
    rejection_reason?: string | null;
    committed_transaction_id?: number | null;
}

export interface ApprovePendingItem {
    pending_transaction_id: number;
    description?: string;
    amount?: number;
    split_rule?: SplitRule;
    participants?: ParticipantShare[];
}

export interface AiImportSettings {
    configured: boolean;
    provider: 'openai' | 'anthropic' | null;
    model: string | null;
    masked_api_key: string | null;
    auto_create_accounts: boolean;
}

export interface BankAccountMapping {
    id: number;
    ledger_id: number;
    external_account_name: string;
    masked_identifier: string | null;
    ownership_type: 'personal' | 'joint';
    account_id: number | null;
    account_name: string | null;
    suggested_account_id: number | null;
    suggested_account_name: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface StatementImport {
    id: string;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    filename: string;
    parsed_count: number;
    pending_count: number;
    duplicate_count: number;
    failed_count: number;
    error_message: string | null;
    processed_at: string | null;
    created_at: string | null;
}

export interface Account {
    id: number;
    ledger_id: number;
    owner_id?: number | null;
    name: string;
    type: 'pool_asset' | 'space_expense' | 'split_clearing' | 'user_funding' | 'user_liability';
    base_budget?: number;
    balance: number;
    owner_is_active?: boolean;
}
