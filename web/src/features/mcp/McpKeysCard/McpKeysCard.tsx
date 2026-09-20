import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
    CheckIcon,
    CopyIcon,
    KeyRoundIcon,
    Loader2Icon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';

import {
    createMcpSignedUrl,
    createMcpToken,
    fetchMcpTokens,
    revokeMcpToken,
    type CreatedMcpTokenResponse,
    type McpSignedUrl,
    type McpToken,
} from '@/api/mcp';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
    InputGroupTextarea,
} from '@/components/ui/input-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

async function copyText(value: string): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(value);
        return true;
    } catch {
        return false;
    }
}

function formatTimestamp(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
}

function CopyBlock({
    id,
    label,
    value,
    multiline = false,
}: {
    id: string;
    label: string;
    value: string;
    multiline?: boolean;
}) {
    const [copied, setCopied] = React.useState(false);

    const onCopy = async () => {
        const ok = await copyText(value);
        if (!ok) {
            return;
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Field>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <InputGroup className={multiline ? 'h-auto items-start' : undefined}>
                {multiline ? (
                    <InputGroupTextarea
                        id={id}
                        readOnly
                        rows={10}
                        value={value}
                        aria-label={label}
                    />
                ) : (
                    <InputGroupInput id={id} readOnly value={value} aria-label={label} />
                )}
                <InputGroupAddon align={multiline ? 'block-end' : 'inline-end'}>
                    <InputGroupButton
                        type="button"
                        size={multiline ? 'xs' : 'icon-xs'}
                        onClick={() => void onCopy()}
                        aria-label={`Copy ${label}`}
                    >
                        {copied ? <CheckIcon /> : <CopyIcon />}
                        {multiline ? (copied ? 'Copied' : 'Copy') : null}
                    </InputGroupButton>
                </InputGroupAddon>
            </InputGroup>
        </Field>
    );
}

function CreatedKeyDialog({
    created,
    onOpenChange,
}: {
    created: CreatedMcpTokenResponse | null;
    onOpenChange: (open: boolean) => void;
}) {
    if (created === null) {
        return null;
    }

    const cursorConfig = JSON.stringify(created.meta.client_config.cursor, null, 2);

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg" showCloseButton>
                <DialogHeader>
                    <DialogTitle>MCP key created</DialogTitle>
                    <DialogDescription>
                        Copy the key now. Poruko will not show it again. Revoke it from this page if it leaks.
                    </DialogDescription>
                </DialogHeader>
                <Alert>
                    <KeyRoundIcon />
                    <AlertTitle>Shown once</AlertTitle>
                    <AlertDescription>
                        Paste the Cursor config into MCP settings, or use the URL plus the Authorization header in Claude or Inspector.
                    </AlertDescription>
                </Alert>
                <FieldGroup className="gap-4">
                    <CopyBlock id="mcp-created-url" label="MCP URL" value={created.meta.mcp_url} />
                    <CopyBlock
                        id="mcp-created-header"
                        label="Authorization header"
                        value={created.meta.client_config.headers.Authorization}
                    />
                    <CopyBlock
                        id="mcp-created-cursor"
                        label="Cursor MCP config"
                        value={cursorConfig}
                        multiline
                    />
                    <CopyBlock
                        id="mcp-created-signed"
                        label={`Signed URL (expires in ${created.meta.signed_url.expires_in_hours}h)`}
                        value={created.meta.signed_url.url}
                    />
                </FieldGroup>
                <DialogFooter showCloseButton />
            </DialogContent>
        </Dialog>
    );
}

export function McpKeysCard() {
    const queryClient = useQueryClient();
    const [createOpen, setCreateOpen] = React.useState(false);
    const [keyName, setKeyName] = React.useState('Cursor');
    const [created, setCreated] = React.useState<CreatedMcpTokenResponse | null>(null);
    const [revokeTarget, setRevokeTarget] = React.useState<McpToken | null>(null);
    const [signedFor, setSignedFor] = React.useState<McpToken | null>(null);
    const [signedHours, setSignedHours] = React.useState('24');
    const [signedResult, setSignedResult] = React.useState<McpSignedUrl | null>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['mcp-tokens'],
        queryFn: fetchMcpTokens,
    });

    const createMutation = useMutation({
        mutationFn: (name: string) => createMcpToken(name),
        onSuccess: (response) => {
            setCreateOpen(false);
            setKeyName('Cursor');
            setCreated(response);
            void queryClient.invalidateQueries({ queryKey: ['mcp-tokens'] });
        },
    });

    const revokeMutation = useMutation({
        mutationFn: (tokenId: number) => revokeMcpToken(tokenId),
        onSuccess: () => {
            setRevokeTarget(null);
            void queryClient.invalidateQueries({ queryKey: ['mcp-tokens'] });
        },
    });

    const signedMutation = useMutation({
        mutationFn: ({ tokenId, hours }: { tokenId: number; hours: number }) =>
            createMcpSignedUrl(tokenId, hours),
        onSuccess: (result) => {
            setSignedResult(result);
        },
    });

    const tokens = data?.data ?? [];
    const mcpUrl = data?.meta.mcp_url ?? 'http://localhost:8000/mcp/poruko';

    return (
        <>
            <Card>
                <CardHeader className="border-b">
                    <CardTitle className="flex items-center gap-2">
                        <KeyRoundIcon />
                        MCP keys
                    </CardTitle>
                    <CardDescription>
                        Create a dedicated key for Cursor, Claude, or Inspector. Keys act as you and still honor this space’s MCP flags. They cannot be used as a general API login.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <p className="text-sm text-muted-foreground">
                        Server URL: <span className="font-medium text-foreground">{mcpUrl}</span>
                    </p>
                    {isLoading ? (
                        <Skeleton className="h-28 w-full" />
                    ) : tokens.length === 0 ? (
                        <Empty className="border">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <KeyRoundIcon />
                                </EmptyMedia>
                                <EmptyTitle>No MCP keys yet</EmptyTitle>
                                <EmptyDescription>
                                    Create a key to copy a ready-to-paste agent config. Do not reuse your browser login token.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Last used</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tokens.map((token) => (
                                    <TableRow key={token.id}>
                                        <TableCell className="font-medium">{token.name}</TableCell>
                                        <TableCell>{formatTimestamp(token.last_used_at)}</TableCell>
                                        <TableCell>{formatTimestamp(token.created_at)}</TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        setSignedResult(null);
                                                        setSignedHours('24');
                                                        setSignedFor(token);
                                                    }}
                                                >
                                                    Signed URL
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => setRevokeTarget(token)}
                                                >
                                                    <Trash2Icon data-icon="inline-start" />
                                                    Revoke
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    <div className="flex justify-end">
                        <Button type="button" onClick={() => setCreateOpen(true)}>
                            <PlusIcon data-icon="inline-start" />
                            Create MCP key
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createMutation.mutate(keyName.trim());
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>Create MCP key</DialogTitle>
                            <DialogDescription>
                                Name the key after the agent that will use it. The secret is shown only once.
                            </DialogDescription>
                        </DialogHeader>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="mcp-key-name">Key name</FieldLabel>
                                <Input
                                    id="mcp-key-name"
                                    value={keyName}
                                    onChange={(event) => setKeyName(event.target.value)}
                                    placeholder="Cursor"
                                    autoFocus
                                    required
                                />
                            </Field>
                        </FieldGroup>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createMutation.isPending || keyName.trim() === ''}>
                                {createMutation.isPending ? (
                                    <Loader2Icon data-icon="inline-start" className="animate-spin" />
                                ) : (
                                    <PlusIcon data-icon="inline-start" />
                                )}
                                Create key
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <CreatedKeyDialog created={created} onOpenChange={(open) => { if (!open) setCreated(null); }} />

            <AlertDialog open={revokeTarget !== null} onOpenChange={(open) => { if (!open) setRevokeTarget(null); }}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Revoke {revokeTarget?.name}?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Agents using this key will stop authenticating immediately. Signed URLs created from it also stop working.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={revokeMutation.isPending}
                            onClick={(event) => {
                                event.preventDefault();
                                if (revokeTarget) {
                                    revokeMutation.mutate(revokeTarget.id);
                                }
                            }}
                        >
                            Revoke key
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Dialog
                open={signedFor !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSignedFor(null);
                        setSignedResult(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Signed MCP URL</DialogTitle>
                        <DialogDescription>
                            Optional for clients that only accept a URL. It expires, and revoking the key invalidates it. Prefer the Bearer key when the client supports headers.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="mcp-signed-hours">Expires in</FieldLabel>
                            <Select value={signedHours} onValueChange={setSignedHours}>
                                <SelectTrigger id="mcp-signed-hours" aria-label="Signed URL lifetime">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">1 hour</SelectItem>
                                    <SelectItem value="24">24 hours</SelectItem>
                                    <SelectItem value="168">7 days</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        {signedResult ? (
                            <CopyBlock id="mcp-signed-url" label="Signed URL" value={signedResult.url} />
                        ) : null}
                    </FieldGroup>
                    <DialogFooter>
                        <Button
                            type="button"
                            disabled={signedMutation.isPending || signedFor === null}
                            onClick={() => {
                                if (signedFor) {
                                    signedMutation.mutate({
                                        tokenId: signedFor.id,
                                        hours: Number(signedHours),
                                    });
                                }
                            }}
                        >
                            {signedMutation.isPending ? (
                                <Loader2Icon data-icon="inline-start" className="animate-spin" />
                            ) : null}
                            Generate signed URL
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
