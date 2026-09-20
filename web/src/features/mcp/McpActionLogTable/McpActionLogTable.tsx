import { useQuery } from '@tanstack/react-query';
import { ScrollTextIcon } from 'lucide-react';

import { fetchMcpActionLogs, type McpActionLog } from '@/api/mcp';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { useLedgerStore } from '@/stores/ledgerStore';

function statusVariant(status: string): 'success' | 'destructive' | 'warning' | 'secondary' {
    if (status === 'ok') {
        return 'success';
    }
    if (status === 'denied' || status === 'unauthenticated') {
        return 'destructive';
    }
    if (status === 'invalid' || status === 'not_found') {
        return 'warning';
    }
    return 'secondary';
}

export function McpActionLogTable() {
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);

    const { data, isPending } = useQuery({
        queryKey: ['mcp-action-log', activeLedgerId],
        queryFn: () => fetchMcpActionLogs(activeLedgerId!),
        enabled: activeLedgerId !== null,
    });

    const logs: McpActionLog[] = data?.data ?? [];

    if (isPending) {
        return <Skeleton className="h-40 w-full" />;
    }

    if (logs.length === 0) {
        return (
            <Empty className="border">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <ScrollTextIcon />
                    </EmptyMedia>
                    <EmptyTitle>No MCP actions yet</EmptyTitle>
                    <EmptyDescription>
                        Tool calls appear here for observability. Pending approvals stay on Transactions.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>When</TableHead>
                    <TableHead>Tool</TableHead>
                    <TableHead>Operation</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Duration</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {logs.map((log) => (
                    <TableRow key={log.id}>
                        <TableCell>{log.created_at ? new Date(log.created_at).toLocaleString() : '—'}</TableCell>
                        <TableCell>{log.tool_name}</TableCell>
                        <TableCell className="capitalize">{log.operation}</TableCell>
                        <TableCell>
                            <Badge variant={statusVariant(log.response_status)}>{log.response_status}</Badge>
                        </TableCell>
                        <TableCell>{log.duration_ms} ms</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
