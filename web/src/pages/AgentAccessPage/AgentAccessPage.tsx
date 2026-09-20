import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    BotIcon,
    CheckCircle2Icon,
    CopyIcon,
    ExternalLinkIcon,
    RefreshCwIcon,
    SaveIcon,
    ShieldAlertIcon,
    SparklesIcon,
} from 'lucide-react';

import {
    fetchA2uiFinanceSummary,
    fetchMcpActionLogs,
    fetchMcpSettings,
    fetchMcpTools,
    updateMcpSettings,
    type McpSettings,
    type McpToolDefinition,
} from '@/api/mcp';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { A2uiFinanceSurface } from '@/features/mcp/A2uiFinanceSurface';
import { useWebMcpTools } from '@/hooks/use-web-mcp-tools';
import { useLedgerStore } from '@/stores/ledgerStore';

const EMPTY_TOOLS: McpToolDefinition[] = [];

const DEFAULT_SETTINGS: Omit<McpSettings, 'endpoint'> = {
    read_enabled: true,
    write_enabled: false,
    destructive_enabled: false,
    require_transaction_approval: true,
};

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'succeeded' || status === 'ready') return 'default';
    if (status === 'failed' || status === 'denied' || status === 'error') return 'destructive';
    return 'secondary';
}

export default function AgentAccessPage() {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);
    const [draft, setDraft] = React.useState<typeof DEFAULT_SETTINGS | null>(null);
    const [copied, setCopied] = React.useState(false);

    const settingsQuery = useQuery({
        queryKey: ['mcp-settings'],
        queryFn: fetchMcpSettings,
    });
    const toolsQuery = useQuery({
        queryKey: ['mcp-tools'],
        queryFn: fetchMcpTools,
    });
    const logsQuery = useQuery({
        queryKey: ['mcp-action-logs'],
        queryFn: fetchMcpActionLogs,
        refetchInterval: 30_000,
    });
    const editableSettings = draft ?? (settingsQuery.data ? {
        read_enabled: settingsQuery.data.read_enabled,
        write_enabled: settingsQuery.data.write_enabled,
        destructive_enabled: settingsQuery.data.destructive_enabled,
        require_transaction_approval: settingsQuery.data.require_transaction_approval,
    } : DEFAULT_SETTINGS);
    const summaryQuery = useQuery({
        queryKey: ['mcp-a2ui-summary', activeLedgerId],
        queryFn: () => fetchA2uiFinanceSummary(activeLedgerId!),
        enabled: activeLedgerId !== null && editableSettings.read_enabled,
    });
    const webMcp = useWebMcpTools(toolsQuery.data ?? EMPTY_TOOLS);

    const settingsMutation = useMutation({
        mutationFn: updateMcpSettings,
        onSuccess: (settings) => {
            setDraft({
                read_enabled: settings.read_enabled,
                write_enabled: settings.write_enabled,
                destructive_enabled: settings.destructive_enabled,
                require_transaction_approval: settings.require_transaction_approval,
            });
            queryClient.setQueryData(['mcp-settings'], settings);
            void queryClient.invalidateQueries({ queryKey: ['mcp-tools'] });
            void queryClient.invalidateQueries({ queryKey: ['mcp-a2ui-summary'] });
        },
    });

    const setSetting = (key: keyof typeof DEFAULT_SETTINGS, value: boolean) => {
        setDraft({ ...editableSettings, [key]: value });
    };

    const copyEndpoint = async () => {
        if (!settingsQuery.data?.endpoint) return;
        await navigator.clipboard.writeText(settingsQuery.data.endpoint);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2_000);
    };

    return (
        <div className="flex w-full flex-col gap-6">
            <div className="flex flex-col gap-1">
                <div className="flex items-center gap-2">
                    <BotIcon className="size-6 text-primary" />
                    <h1 className="text-2xl font-bold tracking-tight">Agent access</h1>
                </div>
                <p className="text-sm text-muted-foreground">
                    Control MCP capabilities, WebMCP exposure, approvals, and action observability.
                </p>
            </div>

            <Alert>
                <ShieldAlertIcon />
                <AlertTitle>Authenticated and policy-scoped</AlertTitle>
                <AlertDescription>
                    Agent actions run as you. Personal accounts owned by another member are never available,
                    even when you have administrative permissions.
                </AlertDescription>
            </Alert>

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Operation permissions</CardTitle>
                        <CardDescription>
                            Each capability class is explicit and enforced by Laravel policies for MCP,
                            WebMCP, and A2UI.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {settingsQuery.isPending ? (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner />
                                Loading permissions…
                            </div>
                        ) : (
                            <FieldGroup>
                                <Field orientation="horizontal">
                                    <FieldContent>
                                        <FieldLabel htmlFor="mcp-read">Read operations</FieldLabel>
                                        <FieldDescription>
                                            Spaces, transactions, accounts, settlements, blueprints, and My Finance.
                                        </FieldDescription>
                                    </FieldContent>
                                    <Switch
                                        id="mcp-read"
                                        checked={editableSettings.read_enabled}
                                        onCheckedChange={(checked) => setSetting('read_enabled', checked)}
                                    />
                                </Field>
                                <Field orientation="horizontal">
                                    <FieldContent>
                                        <FieldLabel htmlFor="mcp-write">Write operations</FieldLabel>
                                        <FieldDescription>
                                            Create and update finance records through existing domain actions.
                                        </FieldDescription>
                                    </FieldContent>
                                    <Switch
                                        id="mcp-write"
                                        checked={editableSettings.write_enabled}
                                        onCheckedChange={(checked) => setSetting('write_enabled', checked)}
                                    />
                                </Field>
                                <Field orientation="horizontal">
                                    <FieldContent>
                                        <FieldLabel htmlFor="mcp-destructive">Destructive operations</FieldLabel>
                                        <FieldDescription>
                                            Delete records or confirm settlement cycles. Keep disabled unless needed.
                                        </FieldDescription>
                                    </FieldContent>
                                    <Switch
                                        id="mcp-destructive"
                                        checked={editableSettings.destructive_enabled}
                                        onCheckedChange={(checked) => setSetting('destructive_enabled', checked)}
                                    />
                                </Field>
                                <Field orientation="horizontal">
                                    <FieldContent>
                                        <FieldLabel htmlFor="mcp-approval">Require transaction approval</FieldLabel>
                                        <FieldDescription>
                                            MCP-created transactions enter the shared approval queue before posting.
                                        </FieldDescription>
                                    </FieldContent>
                                    <Switch
                                        id="mcp-approval"
                                        checked={editableSettings.require_transaction_approval}
                                        onCheckedChange={(checked) => setSetting('require_transaction_approval', checked)}
                                    />
                                </Field>
                            </FieldGroup>
                        )}
                    </CardContent>
                    <CardFooter className="justify-between">
                        <p className="text-xs text-muted-foreground">
                            Safe defaults: reads on, writes and destructive actions off.
                        </p>
                        <Button
                            onClick={() => settingsMutation.mutate(editableSettings)}
                            disabled={settingsQuery.isPending || settingsMutation.isPending}
                        >
                            {settingsMutation.isPending ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <SaveIcon data-icon="inline-start" />
                            )}
                            Save permissions
                        </Button>
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Connection and WebMCP</CardTitle>
                        <CardDescription>
                            Remote MCP uses your Poruko Sanctum bearer token. Browser tools use the same API policies.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-5">
                        <Field>
                            <FieldLabel>Remote MCP endpoint</FieldLabel>
                            <div className="flex items-center gap-2">
                                <code className="min-w-0 flex-1 truncate rounded-md border bg-muted px-3 py-2 text-sm">
                                    {settingsQuery.data?.endpoint ?? 'Loading…'}
                                </code>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Copy MCP endpoint"
                                    onClick={() => void copyEndpoint()}
                                    disabled={!settingsQuery.data?.endpoint}
                                >
                                    {copied ? <CheckCircle2Icon /> : <CopyIcon />}
                                </Button>
                            </div>
                            <FieldDescription>
                                Send <code>Authorization: Bearer &lt;token&gt;</code>. Two-factor enforcement still applies.
                            </FieldDescription>
                        </Field>
                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div className="flex flex-col gap-1">
                                <p className="text-sm font-medium">WebMCP browser registration</p>
                                <p className="text-xs text-muted-foreground">
                                    {webMcp.status === 'unsupported'
                                        ? 'This browser does not expose document.modelContext.'
                                        : `${webMcp.registeredCount} policy-enabled tools are exposed to in-page agents.`}
                                </p>
                            </div>
                            <Badge variant={statusVariant(webMcp.status)}>
                                {webMcp.status}
                            </Badge>
                        </div>
                        {webMcp.error && (
                            <Alert variant="destructive">
                                <AlertDescription>{webMcp.error}</AlertDescription>
                            </Alert>
                        )}
                        <div className="flex flex-wrap gap-2">
                            {(toolsQuery.data ?? EMPTY_TOOLS).map((tool) => (
                                <Badge key={tool.name} variant="outline">
                                    {tool.name}
                                </Badge>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader className="flex-row items-start justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <CardTitle className="flex items-center gap-2">
                            <SparklesIcon className="size-5" />
                            A2UI finance surface
                        </CardTitle>
                        <CardDescription>
                            A2UI v0.9 rendered by the official React client from policy-authorized live resources.
                        </CardDescription>
                    </div>
                    <Badge variant="secondary">A2UI v0.9</Badge>
                </CardHeader>
                <CardContent>
                    {!editableSettings.read_enabled ? (
                        <Alert>
                            <AlertDescription>Enable read operations to render this surface.</AlertDescription>
                        </Alert>
                    ) : summaryQuery.isPending ? (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner />
                            Rendering finance surface…
                        </div>
                    ) : summaryQuery.isError ? (
                        <Alert variant="destructive">
                            <AlertTitle>Finance surface unavailable</AlertTitle>
                            <AlertDescription>
                                Check that an active space is selected and read operations are enabled.
                            </AlertDescription>
                        </Alert>
                    ) : summaryQuery.data ? (
                        <A2uiFinanceSurface messages={summaryQuery.data.messages} />
                    ) : null}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex-row items-start justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <CardTitle>Agent action log</CardTitle>
                        <CardDescription>
                            Observability for MCP, WebMCP, and A2UI. This log never approves transactions.
                        </CardDescription>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => void logsQuery.refetch()}
                        disabled={logsQuery.isFetching}
                    >
                        {logsQuery.isFetching ? (
                            <Spinner data-icon="inline-start" />
                        ) : (
                            <RefreshCwIcon data-icon="inline-start" />
                        )}
                        Refresh
                    </Button>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Action</TableHead>
                                    <TableHead>Transport</TableHead>
                                    <TableHead>Operation</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Space</TableHead>
                                    <TableHead className="text-right">Duration</TableHead>
                                    <TableHead>Started</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {(logsQuery.data ?? []).map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell className="font-medium">{log.tool}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{log.transport}</Badge>
                                        </TableCell>
                                        <TableCell>{log.operation}</TableCell>
                                        <TableCell>
                                            <Badge variant={statusVariant(log.status)}>{log.status}</Badge>
                                        </TableCell>
                                        <TableCell>{log.ledger_name ?? '—'}</TableCell>
                                        <TableCell className="text-right">
                                            {log.duration_ms === null ? '—' : `${log.duration_ms} ms`}
                                        </TableCell>
                                        <TableCell>{new Date(log.started_at).toLocaleString()}</TableCell>
                                    </TableRow>
                                ))}
                                {!logsQuery.isPending && (logsQuery.data?.length ?? 0) === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                            No agent actions yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
                <CardFooter className="justify-end">
                    <a
                        href="https://modelcontextprotocol.io/"
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        Model Context Protocol
                        <ExternalLinkIcon className="size-3" />
                    </a>
                </CardFooter>
            </Card>
        </div>
    );
}
