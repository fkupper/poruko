import { Link } from 'react-router-dom';
import { ArrowLeftIcon, ScrollTextIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { McpActionLogTable } from '@/features/mcp/McpActionLogTable/McpActionLogTable';

export default function McpActionLogPage() {
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
