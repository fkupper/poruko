import client from '@/api/client';

export type McpPostMode = 'direct' | 'approval_queue';

export interface McpSettings {
    enabled: boolean;
    allow_read: boolean;
    allow_write: boolean;
    allow_destructive: boolean;
    post_mode: McpPostMode;
}

export interface UpdateMcpSettingsPayload extends McpSettings {
    destructive_ack?: boolean;
}

export interface McpActionLog {
    id: number;
    user_id: number;
    user_name?: string | null;
    ledger_id: number | null;
    tool_name: string;
    operation: 'read' | 'write' | 'destructive';
    request_payload: Record<string, unknown> | null;
    response_status: string;
    duration_ms: number;
    created_at: string | null;
}

export interface A2uiMessage {
    version: 'v0.9' | 'v0.9.1';
    [key: string]: unknown;
}

export interface A2uiSurfaceResponse {
    protocol: 'a2ui';
    version: 'v0.9';
    surface_id: string;
    messages: A2uiMessage[];
}

export async function fetchMcpSettings(ledgerId: number): Promise<McpSettings> {
    const { data } = await client.get<{ data: McpSettings }>(`/ledgers/${ledgerId}/mcp-settings`);
    return data.data;
}

export async function updateMcpSettings(
    ledgerId: number,
    payload: UpdateMcpSettingsPayload,
): Promise<McpSettings> {
    const { data } = await client.put<{ data: McpSettings }>(`/ledgers/${ledgerId}/mcp-settings`, payload);
    return data.data;
}

export async function fetchMcpActionLogs(
    ledgerId: number,
    params?: { page?: number; per_page?: number },
): Promise<{ data: McpActionLog[]; meta?: { current_page: number; last_page: number; total: number } }> {
    const { data } = await client.get<{
        data: McpActionLog[];
        meta?: { current_page: number; last_page: number; total: number };
    }>(`/ledgers/${ledgerId}/mcp-action-logs`, { params });

    return data;
}

export async function fetchPendingApprovalsA2ui(
    ledgerId: number,
): Promise<A2uiSurfaceResponse> {
    const { data } = await client.get<{ data: A2uiSurfaceResponse }>(
        `/ledgers/${ledgerId}/mcp-a2ui/pending-approvals`,
    );

    return data.data;
}
