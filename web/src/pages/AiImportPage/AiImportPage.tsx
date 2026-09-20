import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
    AlertTriangleIcon,
    ArrowRightIcon,
    BotIcon,
    CheckCircle2Icon,
    FileSpreadsheetIcon,
    KeyRoundIcon,
    LinkIcon,
    PlusIcon,
    UploadCloudIcon,
} from 'lucide-react';

import { createAccount, fetchAccounts } from '@/api/accounts';
import {
    fetchAiImportSettings,
    fetchBankAccountMappings,
    fetchStatementImports,
    saveAiImportSettings,
    updateBankAccountMapping,
    uploadBankStatement,
} from '@/api/ingestion';
import type { AiProvider, BankAccountMapping, StatementImport, StatementImportStage } from '@/api/types';
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
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { useAuthStore } from '@/stores/authStore';
import { useLedgerStore } from '@/stores/ledgerStore';

function importStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'completed') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'processing') return 'secondary';
    return 'outline';
}

function importStageLabel(item: StatementImport): string {
    const stage: StatementImportStage | string = item.stage ?? item.status;

    if (stage === 'parsing') return 'Extracting transactions';
    if (stage === 'mapping_accounts') return 'Matching bank accounts';
    if (stage === 'processing_transactions') return 'Creating proposals';
    if (stage === 'queued') return 'Waiting in queue';
    if (stage === 'completed') return 'Completed';
    if (stage === 'failed') return 'Failed';

    return item.status;
}

function importProgressValue(item: StatementImport): number | null {
    if (item.status === 'completed') return 100;
    if (item.status === 'failed') return null;

    const current = item.progress_current ?? 0;
    const total = item.progress_total ?? 0;

    if (total > 0) {
        return Math.round((current / total) * 100);
    }

    if (item.status === 'processing') {
        return null;
    }

    return 0;
}

function isImportInProgress(item: StatementImport): boolean {
    return item.status === 'queued' || item.status === 'processing';
}

function isAiProvider(value: string): value is AiProvider {
    return value === 'openai' || value === 'anthropic' || value === 'openai_compatible';
}

function modelPlaceholder(provider: AiProvider): string {
    if (provider === 'anthropic') return 'claude-haiku-4-5';
    if (provider === 'openai_compatible') return 'llama3.1';
    return 'gpt-4.1-mini';
}

function apiKeyHelp(options: {
    keyRequired: boolean;
    configured: boolean;
    isCompatible: boolean;
    maskedSuffix?: string;
}): string {
    if (options.keyRequired && options.configured) {
        return 'Enter an API key for the new provider or endpoint. Existing keys are never reused on a different host.';
    }

    if (options.keyRequired && options.isCompatible) {
        return 'Required. Local servers can use a short dummy key if they do not authenticate.';
    }

    if (options.keyRequired) {
        return 'Required before a statement can be uploaded.';
    }

    return `A key ending in ${options.maskedSuffix ?? ''} is configured. Leave blank to keep it.`;
}

export default function AiImportPage() {
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);
    const user = useAuthStore((state) => state.user);
    const queryClient = useQueryClient();
    const [provider, setProvider] = React.useState<AiProvider>('openai');
    const [model, setModel] = React.useState('');
    const [baseUrl, setBaseUrl] = React.useState('');
    const [apiKey, setApiKey] = React.useState('');
    const [autoCreateAccounts, setAutoCreateAccounts] = React.useState(false);
    const [statement, setStatement] = React.useState<File | null>(null);

    const settingsQuery = useQuery({
        queryKey: ['ai-import-settings', activeLedgerId],
        queryFn: () => fetchAiImportSettings(activeLedgerId!),
        enabled: activeLedgerId !== null,
    });

    const mappingsQuery = useQuery({
        queryKey: ['ai-import-mappings', activeLedgerId],
        queryFn: () => fetchBankAccountMappings(activeLedgerId!),
        enabled: activeLedgerId !== null,
    });

    const importsQuery = useQuery({
        queryKey: ['statement-imports', activeLedgerId],
        queryFn: () => fetchStatementImports(activeLedgerId!),
        enabled: activeLedgerId !== null,
        refetchInterval: (query) => {
            const imports = query.state.data;
            return imports?.some((item) => item.status === 'queued' || item.status === 'processing')
                ? 2000
                : false;
        },
    });

    const previousImportStatuses = React.useRef<Record<string, string>>({});

    React.useEffect(() => {
        const imports = importsQuery.data;
        if (!imports) return;

        let shouldRefreshQueue = false;

        for (const item of imports) {
            const previous = previousImportStatuses.current[item.id];
            if (
                previous !== undefined
                && previous !== item.status
                && (item.status === 'completed' || item.status === 'failed')
            ) {
                shouldRefreshQueue = true;
            }
            previousImportStatuses.current[item.id] = item.status;
        }

        if (!shouldRefreshQueue) return;

        queryClient.invalidateQueries({ queryKey: ['pending-transactions', activeLedgerId] });
        queryClient.invalidateQueries({ queryKey: ['ai-import-mappings', activeLedgerId] });
        queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
    }, [importsQuery.data, activeLedgerId, queryClient]);

    const accountsQuery = useQuery({
        queryKey: ['accounts', activeLedgerId],
        queryFn: () => fetchAccounts(activeLedgerId!),
        enabled: activeLedgerId !== null,
    });

    React.useEffect(() => {
        const settings = settingsQuery.data;
        if (!settings) return;

        // eslint-disable-next-line react-hooks/set-state-in-effect -- hydrate editable fields from server state
        setProvider(settings.provider ?? 'openai');
        setModel(settings.model ?? '');
        setBaseUrl(settings.base_url ?? '');
        setAutoCreateAccounts(settings.auto_create_accounts);
        setApiKey('');
    }, [settingsQuery.data]);

    const saveMutation = useMutation({
        mutationFn: () => {
            if (activeLedgerId === null) throw new Error('Select a space first.');

            return saveAiImportSettings(activeLedgerId, {
                provider,
                model: model || undefined,
                api_key: apiKey || undefined,
                auto_create_accounts: autoCreateAccounts,
                ...(provider === 'openai_compatible' ? { base_url: baseUrl } : {}),
            });
        },
        onSuccess: (settings) => {
            queryClient.setQueryData(['ai-import-settings', activeLedgerId], settings);
            setApiKey('');
        },
    });

    const uploadMutation = useMutation({
        mutationFn: () => {
            if (activeLedgerId === null || statement === null) {
                throw new Error('Choose a bank statement first.');
            }

            return uploadBankStatement(activeLedgerId, statement);
        },
        onSuccess: () => {
            setStatement(null);
            queryClient.invalidateQueries({ queryKey: ['statement-imports', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['ai-import-mappings', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['pending-transactions', activeLedgerId] });
        },
    });

    const mappingMutation = useMutation({
        mutationFn: async ({
            mapping,
            accountId,
            create,
        }: {
            mapping: BankAccountMapping;
            accountId?: number | null;
            create?: boolean;
        }) => {
            if (activeLedgerId === null) throw new Error('Select a space first.');

            let mappedAccountId = accountId ?? null;

            if (create) {
                const account = await createAccount(activeLedgerId, {
                    name: mapping.masked_identifier
                        ? `${mapping.external_account_name} ${mapping.masked_identifier}`
                        : mapping.external_account_name,
                    type: mapping.ownership_type === 'joint' ? 'pool_asset' : 'user_funding',
                });
                mappedAccountId = account.id;
            }

            return updateBankAccountMapping(activeLedgerId, mapping.id, mappedAccountId);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['ai-import-mappings', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['accounts', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['pending-transactions', activeLedgerId] });
        },
    });

    const accounts = accountsQuery.data ?? [];
    const mappings = mappingsQuery.data ?? [];
    const imports = importsQuery.data ?? [];
    const settings = settingsQuery.data;
    const isCompatible = provider === 'openai_compatible';
    const providerChanged = settings?.configured === true && settings.provider !== provider;
    const endpointChanged = isCompatible
        && settings?.configured === true
        && (settings.base_url ?? '') !== baseUrl.trim();
    const keyRequired = settings?.configured !== true || providerChanged || endpointChanged;
    const minKeyLength = isCompatible ? 4 : 12;
    const canSave = (!keyRequired || apiKey.length >= minKeyLength)
        && (!isCompatible || (baseUrl.trim() !== '' && model.trim() !== ''));

    return (
        <div className="flex w-full flex-col gap-6">
            <div className="flex flex-col gap-1">
                <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <BotIcon className="size-6 text-primary" />
                    AI statement import
                </h1>
                <p className="text-sm text-muted-foreground">
                    Parse bank statements with your own provider key, then approve every expense before it reaches the ledger.
                </p>
            </div>

            {settingsQuery.isError && (
                <Alert variant="destructive">
                    <AlertTitle>AI import is unavailable</AlertTitle>
                    <AlertDescription>
                        You need the AI ingestion permission in this space to configure a provider or upload statements.
                    </AlertDescription>
                </Alert>
            )}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <KeyRoundIcon className="size-5" />
                        Your AI provider
                    </CardTitle>
                    <CardDescription>
                        Keys are encrypted on the server, sent only to the host you configure, and never returned to this browser.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup className="grid gap-4 md:grid-cols-2">
                        <Field>
                            <FieldLabel>Provider</FieldLabel>
                            <Select
                                value={provider}
                                onValueChange={(value) => {
                                    if (!isAiProvider(value)) return;

                                    setProvider(value);
                                    setApiKey('');

                                    if (value === settings?.provider) {
                                        setModel(settings.model ?? '');
                                        setBaseUrl(settings.base_url ?? '');
                                        return;
                                    }

                                    setModel('');
                                    setBaseUrl('');
                                }}
                            >
                                <SelectTrigger className="w-full" aria-label="Provider">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Cloud</SelectLabel>
                                        <SelectItem value="openai">OpenAI</SelectItem>
                                        <SelectItem value="anthropic">Anthropic</SelectItem>
                                    </SelectGroup>
                                    <SelectSeparator />
                                    <SelectGroup>
                                        <SelectLabel>Custom</SelectLabel>
                                        <SelectItem value="openai_compatible">
                                            OpenAI-compatible / self-hosted
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldDescription>
                                OpenAI is the Chat Completions protocol. Anthropic stays on its own Messages API.
                            </FieldDescription>
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="ai-model">
                                {isCompatible ? 'Model' : 'Model override'}
                            </FieldLabel>
                            <Input
                                id="ai-model"
                                value={model}
                                onChange={(event) => setModel(event.target.value)}
                                placeholder={modelPlaceholder(provider)}
                            />
                            <FieldDescription>
                                {isCompatible
                                    ? 'Required. Use the model name your endpoint expects.'
                                    : 'Optional. Leave blank to use the recommended model.'}
                            </FieldDescription>
                        </Field>
                        {isCompatible && (
                            <Field className="md:col-span-2">
                                <FieldLabel htmlFor="ai-base-url">Base URL</FieldLabel>
                                <Input
                                    id="ai-base-url"
                                    value={baseUrl}
                                    onChange={(event) => setBaseUrl(event.target.value)}
                                    placeholder="https://api.deepseek.com/v1"
                                    autoComplete="off"
                                    spellCheck={false}
                                />
                                <FieldDescription>
                                    OpenAI-compatible Chat Completions root, including /v1 when your server uses that path.
                                    Localhost and private networks are allowed, for example http://127.0.0.1:11434/v1.
                                </FieldDescription>
                            </Field>
                        )}
                        {isCompatible && (
                            <Alert className="md:col-span-2">
                                <AlertTriangleIcon />
                                <AlertTitle>Third-party compatibility is not guaranteed</AlertTitle>
                                <AlertDescription>
                                    Cloud clones and self-hosted servers (DeepSeek, Groq, Ollama, vLLM, and others) differ in
                                    JSON mode, timeouts, and authentication. Your API key is sent only to this base URL,
                                    never to OpenAI or Anthropic.
                                </AlertDescription>
                            </Alert>
                        )}
                        <Field className="md:col-span-2">
                            <FieldLabel htmlFor="ai-api-key">
                                {settings?.configured && !keyRequired ? 'Replace API key' : 'API key'}
                            </FieldLabel>
                            <Input
                                id="ai-api-key"
                                type="password"
                                autoComplete="off"
                                value={apiKey}
                                onChange={(event) => setApiKey(event.target.value)}
                                placeholder={settings?.masked_api_key && !keyRequired
                                    ? settings.masked_api_key
                                    : 'Enter your provider API key'}
                            />
                            <FieldDescription>
                                {apiKeyHelp({
                                    keyRequired,
                                    configured: settings?.configured === true,
                                    isCompatible,
                                    maskedSuffix: settings?.masked_api_key?.slice(-4),
                                })}
                            </FieldDescription>
                        </Field>
                        <Field orientation="horizontal" className="md:col-span-2">
                            <FieldContent>
                                <FieldLabel htmlFor="auto-create-bank-accounts">
                                    Auto-create new bank accounts
                                </FieldLabel>
                                <FieldDescription>
                                    Create personal funding or shared pool accounts when no safe match exists.
                                </FieldDescription>
                            </FieldContent>
                            <Switch
                                id="auto-create-bank-accounts"
                                checked={autoCreateAccounts}
                                onCheckedChange={setAutoCreateAccounts}
                            />
                        </Field>
                    </FieldGroup>

                    {saveMutation.isError && (
                        <Alert variant="destructive" className="mt-4">
                            <AlertTitle>Settings could not be saved</AlertTitle>
                            <AlertDescription>{saveMutation.error.message}</AlertDescription>
                        </Alert>
                    )}
                </CardContent>
                <CardFooter className="justify-end">
                    <Button
                        onClick={() => saveMutation.mutate()}
                        disabled={saveMutation.isPending || !canSave}
                    >
                        {saveMutation.isPending ? <Spinner data-icon="inline-start" /> : <CheckCircle2Icon data-icon="inline-start" />}
                        Save provider settings
                    </Button>
                </CardFooter>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileSpreadsheetIcon className="size-5" />
                        Upload a bank statement
                    </CardTitle>
                    <CardDescription>
                        CSV, TSV, and text statements up to 10 MB are processed in the background.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Field>
                        <FieldLabel htmlFor="statement-file">Statement file</FieldLabel>
                        <Input
                            id="statement-file"
                            type="file"
                            accept=".csv,.tsv,.txt,text/csv,text/plain"
                            onChange={(event) => setStatement(event.target.files?.[0] ?? null)}
                            disabled={!settings?.configured || uploadMutation.isPending}
                        />
                        <FieldDescription>
                            AI results become pending proposals. Uploading never changes balances directly.
                        </FieldDescription>
                    </Field>

                    {uploadMutation.isError && (
                        <Alert variant="destructive" className="mt-4">
                            <AlertDescription>{uploadMutation.error.message}</AlertDescription>
                        </Alert>
                    )}
                </CardContent>
                <CardFooter className="justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                        {statement?.name ?? 'No statement selected'}
                    </p>
                    <Button
                        onClick={() => uploadMutation.mutate()}
                        disabled={!settings?.configured || !statement || uploadMutation.isPending}
                    >
                        {uploadMutation.isPending
                            ? <Spinner data-icon="inline-start" />
                            : <UploadCloudIcon data-icon="inline-start" />}
                        Queue import
                    </Button>
                </CardFooter>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <LinkIcon className="size-5" />
                        Bank account associations
                    </CardTitle>
                    <CardDescription>
                        Personal accounts map to your funding accounts; joint accounts map to the shared pool.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {mappings.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon"><LinkIcon /></EmptyMedia>
                                <EmptyTitle>No bank accounts discovered yet</EmptyTitle>
                                <EmptyDescription>
                                    Upload a statement and the AI will suggest associations here.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Bank account</TableHead>
                                    <TableHead>Ownership</TableHead>
                                    <TableHead>Poruko account</TableHead>
                                    <TableHead className="text-right">Create</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {mappings.map((mapping) => {
                                    const availableAccounts = accounts.filter((account) => (
                                        mapping.ownership_type === 'joint'
                                            ? account.type === 'pool_asset'
                                            : account.type === 'user_funding' && account.owner_id === user?.id
                                    ));

                                    return (
                                        <TableRow key={mapping.id}>
                                            <TableCell>
                                                <div className="flex flex-col gap-1">
                                                    <span className="font-medium">{mapping.external_account_name}</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {mapping.masked_identifier ?? 'Identifier unavailable'}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {mapping.ownership_type === 'joint' ? 'Joint' : 'Personal'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex min-w-56 flex-col gap-1">
                                                    <Select
                                                        value={mapping.account_id ? String(mapping.account_id) : 'unmapped'}
                                                        onValueChange={(value) => mappingMutation.mutate({
                                                            mapping,
                                                            accountId: value === 'unmapped' ? null : Number(value),
                                                        })}
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Choose an account" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                <SelectItem value="unmapped">Not associated</SelectItem>
                                                                {availableAccounts.map((account) => (
                                                                    <SelectItem key={account.id} value={String(account.id)}>
                                                                        {account.name}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                    {mapping.suggested_account_id && (
                                                        <Button
                                                            variant="link"
                                                            size="sm"
                                                            className="h-auto justify-start p-0"
                                                            onClick={() => mappingMutation.mutate({
                                                                mapping,
                                                                accountId: mapping.suggested_account_id,
                                                            })}
                                                        >
                                                            Use suggestion: {mapping.suggested_account_name}
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={mappingMutation.isPending}
                                                    onClick={() => mappingMutation.mutate({ mapping, create: true })}
                                                >
                                                    <PlusIcon data-icon="inline-start" />
                                                    Create account
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Recent statement imports</CardTitle>
                    <CardDescription>
                        Duplicate rows are skipped before a pending proposal is created.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {imports.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No statements imported yet.</p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {imports.map((item) => {
                                const inProgress = isImportInProgress(item);
                                const progressValue = importProgressValue(item);

                                return (
                                    <div
                                        key={item.id}
                                        className="flex flex-col justify-between gap-2 rounded-lg border p-3 sm:flex-row sm:items-center"
                                    >
                                        <div className="flex min-w-0 flex-1 flex-col gap-2">
                                            <span className="font-medium">{item.filename}</span>
                                            {inProgress ? (
                                                <div className="flex flex-col gap-1">
                                                    <span className="text-xs text-muted-foreground">
                                                        {importStageLabel(item)}
                                                        {item.progress_total
                                                            ? ` · ${item.progress_current ?? 0} of ${item.progress_total}`
                                                            : ''}
                                                    </span>
                                                    <Progress
                                                        value={progressValue}
                                                        aria-label={importStageLabel(item)}
                                                    />
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    {item.pending_count} pending · {item.duplicate_count} duplicates · {item.failed_count} skipped
                                                </span>
                                            )}
                                        </div>
                                        <Badge variant={importStatusVariant(item.status)}>
                                            {inProgress ? importStageLabel(item) : item.status}
                                        </Badge>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
                <CardFooter className="justify-end">
                    <Button asChild>
                        <Link to="/transactions">
                            Review pending transactions
                            <ArrowRightIcon data-icon="inline-end" />
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}
