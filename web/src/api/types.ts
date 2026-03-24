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
