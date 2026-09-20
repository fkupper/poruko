import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeftIcon, ScrollTextIcon, SparklesIcon } from 'lucide-react';

import { fetchPendingApprovalsA2ui } from '@/api/mcp';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { A2uiSurfaceRenderer } from '@/features/mcp/A2uiSurface';
import { McpActionLogTable } from '@/features/mcp/McpActionLogTable/McpActionLogTable';
import { useLedgerStore } from '@/stores/ledgerStore';

export default function McpActionLogPage() {
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);
    const a2uiQuery = useQuery({
        queryKey: ['mcp-a2ui-pending', activeLedgerId],
        queryFn: () => fetchPendingApprovalsA2ui(activeLedgerId!),
        enabled: activeLedgerId !== null,
    });

    return (
        <div className="flex flex-col gap-6 w-full">
            <div className="flex flex-col gap-2">
                <Button variant="ghost" size="sm" asChild className="w-fit">
                    <Link to="/settings">
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to settings
                    </Link>
                </Button>
                <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                    <ScrollTextIcon className="text-primary" />
                    MCP action log
                </h1>
                <p className="text-sm text-muted-foreground">
                    Observability for MCP tool calls. This is not the pending-transaction approval queue.
                </p>
            </div>
            <Card>
                <CardHeader className="flex-row items-start justify-between gap-4 border-b">
                    <div className="flex flex-col gap-1">
                        <CardTitle className="flex items-center gap-2">
                            <SparklesIcon />
                            A2UI pending approval surface
                        </CardTitle>
                        <CardDescription>
                            Official A2UI v0.9 rendering of the same policy-protected pending resource used by MCP.
                        </CardDescription>
                    </div>
                    <Badge variant="secondary">A2UI v0.9</Badge>
                </CardHeader>
                <CardContent>
                    {a2uiQuery.isPending ? (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner />
                            Rendering A2UI surface…
                        </div>
                    ) : a2uiQuery.isError ? (
                        <Alert variant="destructive">
                            <AlertDescription>
                                Enable MCP read access for this space to render the A2UI surface.
                            </AlertDescription>
                        </Alert>
                    ) : a2uiQuery.data ? (
                        <A2uiSurfaceRenderer messages={a2uiQuery.data.messages} />
                    ) : null}
                </CardContent>
            </Card>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle>Recent actions</CardTitle>
                    <CardDescription>
                        Review who invoked which tool. Approve or reject proposals on the Transactions page.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <McpActionLogTable />
                </CardContent>
            </Card>
        </div>
    );
}
