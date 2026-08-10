import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchPendingTransactions, uploadBankStatement, approvePendingTransactions } from '@/api/ingestion';
import { fetchAccounts } from '@/api/accounts';
import { centsToCurrency } from '@/lib/currency';
import { useLedgerStore } from '@/stores/ledgerStore';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    BotIcon,
    CheckCircle2Icon,
    FileSpreadsheetIcon,
    KeyIcon,
    Loader2Icon,
    SparklesIcon,
    UploadCloudIcon,
} from 'lucide-react';
import type { ApprovePendingItem } from '@/api/types';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export default function IngestionPage() {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((s) => s.activeLedgerId);
    
    const [apiKey, setApiKey] = React.useState(() => localStorage.getItem('byok_llm_key') || '');
    const [selectedAccount, setSelectedAccount] = React.useState<number | null>(() => {
        const saved = localStorage.getItem(`ai_target_account_${activeLedgerId}`);
        return saved ? Number(saved) : null;
    });
    const [uploading, setUploading] = React.useState(false);
    const [uploadMsg, setUploadMsg] = React.useState<string | null>(null);

    // Fetch pending AI transactions
    const { data: pendingTx = [], isPending } = useQuery({
        queryKey: ['pending-ingestion', activeLedgerId],
        queryFn: () => fetchPendingTransactions(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    // Fetch accounts for target upload selection
    const { data: accounts = [] } = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: !!activeLedgerId,
    });

    React.useEffect(() => {
        if (accounts.length > 0 && selectedAccount === null) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setSelectedAccount(accounts[0].id);
        }
    }, [accounts, selectedAccount]);

    React.useEffect(() => {
        if (selectedAccount !== null && activeLedgerId !== null) {
            localStorage.setItem(`ai_target_account_${activeLedgerId}`, String(selectedAccount));
        }
    }, [selectedAccount, activeLedgerId]);

    const approveMutation = useMutation({
        mutationFn: async (txs: ApprovePendingItem[]) => {
            if (!activeLedgerId) return;
            await approvePendingTransactions(activeLedgerId, txs);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['pending-ingestion', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['transactions', activeLedgerId] });
        },
    });

    const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file || !activeLedgerId) return;

        setUploading(true);
        setUploadMsg(null);
        try {
            const res = await uploadBankStatement(activeLedgerId, file, selectedAccount || 1);
            setUploadMsg(res.message || 'Statement uploaded & queued for processing!');
            queryClient.invalidateQueries({ queryKey: ['pending-ingestion', activeLedgerId] });
        } catch (err) {
            setUploadMsg((err as Error).message || 'Failed to upload bank statement.');
        } finally {
            setUploading(false);
            if (e.target) {
                e.target.value = '';
            }
        }
    };

    return (
        <div className="space-y-6 w-full">
            <div>
                <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                    <SparklesIcon className="size-6 text-primary" />
                    AI Ingestion
                </h1>
                <p className="text-sm text-muted-foreground">
                    Configure Bring-Your-Own-Key (BYOK) AI parsing, upload CSV statements, and review the approval queue.
                </p>
            </div>

            {/* BYOK Configuration Card */}
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-base font-semibold text-foreground flex items-center gap-2">
                        <KeyIcon className="size-4 text-amber-500" />
                        Bring Your Own Key (BYOK) LLM Provider
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <p className="text-xs text-muted-foreground">
                        Provide your OpenAI or Anthropic API Key to parse uploaded bank statements into double-entry postings.
                    </p>
                    <div className="flex gap-2">
                        <Input
                            type="password"
                            placeholder="sk-proj-..."
                            value={apiKey}
                            onChange={(e) => setApiKey(e.target.value)}
                            className="h-10 flex-1 font-mono text-sm bg-transparent"
                        />
                        <Button variant="secondary" onClick={() => {
                            localStorage.setItem('byok_llm_key', apiKey);
                            alert('API Key saved locally for statement ingestion.');
                        }}>
                            Save Key
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Bank Statement Upload Dropzone */}
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-base font-semibold text-foreground flex items-center gap-2">
                        <FileSpreadsheetIcon className="size-4 text-inflow" />
                        Bank Statement CSV Ingestion
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">
                                Target Account
                            </label>
                            <Select
                                value={selectedAccount ? String(selectedAccount) : undefined}
                                onValueChange={(val) => setSelectedAccount(Number(val))}
                            >
                                <SelectTrigger className="h-10 w-full bg-background">
                                    <SelectValue placeholder="Select account" />
                                </SelectTrigger>
                                <SelectContent>
                                    {accounts.map((acc) => (
                                        <SelectItem key={acc.id} value={String(acc.id)}>
                                            {acc.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="text-xs font-medium text-muted-foreground block mb-1">
                                Upload Statement CSV
                            </label>
                            <label className="flex h-10 w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-input bg-muted/20 text-xs font-medium hover:bg-muted/40 transition-colors">
                                {uploading ? (
                                    <Loader2Icon className="size-4 animate-spin" />
                                ) : (
                                    <UploadCloudIcon className="size-4 text-primary" />
                                )}
                                {uploading ? 'Processing CSV with AI...' : 'Choose CSV File'}
                                <input
                                    type="file"
                                    accept=".csv"
                                    onChange={handleFileUpload}
                                    disabled={uploading}
                                    className="hidden"
                                />
                            </label>
                        </div>
                    </div>

                    {uploadMsg && (
                        <p className="text-xs font-medium text-inflow">
                            {uploadMsg}
                        </p>
                    )}
                </CardContent>
            </Card>

            {/* Approval Queue Table (Propose-Then-Commit) */}
            <div className="space-y-3">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h2 className="text-base font-semibold text-foreground flex items-center gap-2">
                            <BotIcon className="size-5 text-primary" />
                            Pending AI Approval Queue ("Propose-Then-Commit")
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Review AI suggested split rules, raw descriptions, and rationale before committing to ledger.
                        </p>
                    </div>
                    {pendingTx.length > 0 && (
                        <Button
                            size="sm"
                            onClick={() => approveMutation.mutate(pendingTx.map((t) => ({ pending_transaction_id: t.id })))}
                            disabled={approveMutation.isPending}
                            className="gap-2 shrink-0 bg-inflow hover:bg-inflow/90 text-white"
                        >
                            <CheckCircle2Icon className="size-4" />
                            Approve All ({pendingTx.length})
                        </Button>
                    )}
                </div>

                {isPending ? (
                    <div className="h-40 rounded-xl border bg-muted/20 animate-pulse flex items-center justify-center text-sm text-muted-foreground">
                        Loading pending approval queue...
                    </div>
                ) : pendingTx.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground text-sm space-y-1">
                        <CheckCircle2Icon className="size-8 text-inflow mx-auto" />
                        <p className="font-medium">No transactions awaiting review</p>
                        <p className="text-xs">Upload a CSV above to run AI categorization.</p>
                    </div>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Raw Statement Snippet</TableHead>
                                <TableHead>Suggested Categorization</TableHead>
                                <TableHead>Split Rule</TableHead>
                                <TableHead>AI Rationale</TableHead>
                                <TableHead className="text-right">Amount</TableHead>
                                <TableHead className="text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pendingTx.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                        {item.raw_description}
                                        <div className="text-[10px]">{item.date}</div>
                                    </TableCell>
                                    <TableCell className="font-medium text-foreground">
                                        {item.suggested_description}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className="capitalize text-[11px]">
                                            {item.suggested_split_rule}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-xs text-muted-foreground max-w-xs truncate">
                                        {item.rationale || 'High-confidence AI categorization based on payee keywords.'}
                                    </TableCell>
                                    <TableCell className="text-right font-mono font-semibold text-foreground">
                                        {centsToCurrency(item.suggested_amount)}
                                    </TableCell>
                                    <TableCell className="text-right space-x-1">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                approveMutation.mutate([
                                                    {
                                                        pending_transaction_id: item.id,
                                                        description: item.suggested_description,
                                                        amount: item.suggested_amount,
                                                        split_rule: item.suggested_split_rule,
                                                    },
                                                ])
                                            }
                                            className="text-inflow hover:bg-inflow/10"
                                        >
                                            <CheckCircle2Icon className="size-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </div>
    );
}
