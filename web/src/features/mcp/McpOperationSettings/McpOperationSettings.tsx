import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm, Controller, type UseFormSetError } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import axios from 'axios';
import { Link } from 'react-router-dom';
import { AlertTriangleIcon, BotIcon, CheckCircle2Icon, Loader2Icon, SaveIcon } from 'lucide-react';

import { fetchMcpSettings, updateMcpSettings } from '@/api/mcp';
import type { ApiError } from '@/api/types';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldContent, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { getModelContext } from '@/features/mcp/webmcp/getModelContext';
import { useLedgerStore } from '@/stores/ledgerStore';

const mcpSchema = z.object({
    enabled: z.boolean(),
    allow_read: z.boolean(),
    allow_write: z.boolean(),
    allow_destructive: z.boolean(),
    post_mode: z.enum(['direct', 'approval_queue']),
    destructive_ack: z.boolean().optional(),
}).refine((data) => !data.allow_destructive || data.destructive_ack, {
    message: 'You must acknowledge the risks to enable destructive MCP operations.',
    path: ['destructive_ack'],
});

type McpFormValues = z.infer<typeof mcpSchema>;

const FORM_API_FIELDS = [
    'enabled',
    'allow_read',
    'allow_write',
    'allow_destructive',
    'post_mode',
    'destructive_ack',
] as const satisfies ReadonlyArray<keyof McpFormValues>;

function applyApiErrors(error: unknown, setError: UseFormSetError<McpFormValues>): void {
    if (!axios.isAxiosError<ApiError>(error)) {
        setError('root', { message: 'Failed to save MCP settings. Please try again.' });
        return;
    }

    const data = error.response?.data;
    const fieldErrors = data?.errors ?? {};
    let mappedField = false;

    for (const [field, messages] of Object.entries(fieldErrors)) {
        const message = messages[0];
        if (!message) continue;

        if ((FORM_API_FIELDS as readonly string[]).includes(field)) {
            setError(field as (typeof FORM_API_FIELDS)[number], { message });
            mappedField = true;
        }
    }

    setError('root', {
        message: data?.message
            ?? (mappedField ? 'Please fix the highlighted fields and try again.' : 'Failed to save MCP settings. Please try again.'),
    });
}

export function McpOperationSettings() {
    const queryClient = useQueryClient();
    const activeLedgerId = useLedgerStore((state) => state.activeLedgerId);
    const [saveSuccess, setSaveSuccess] = React.useState(false);
    const webMcpSupported = React.useMemo(() => getModelContext() !== null, []);

    const { data: settings, isLoading } = useQuery({
        queryKey: ['mcp-settings', activeLedgerId],
        queryFn: () => fetchMcpSettings(activeLedgerId!),
        enabled: activeLedgerId !== null,
    });

    const form = useForm<McpFormValues>({
        resolver: zodResolver(mcpSchema),
        defaultValues: {
            enabled: false,
            allow_read: true,
            allow_write: false,
            allow_destructive: false,
            post_mode: 'approval_queue',
            destructive_ack: false,
        },
    });
    const { setError, clearErrors, formState: { errors } } = form;

    React.useEffect(() => {
        if (!settings) {
            return;
        }

        form.reset({
            enabled: settings.enabled,
            allow_read: settings.allow_read,
            allow_write: settings.allow_write,
            allow_destructive: settings.allow_destructive,
            post_mode: settings.post_mode,
            destructive_ack: settings.allow_destructive,
        });
    }, [form, settings]);

    const mutation = useMutation({
        mutationFn: async (values: McpFormValues) => {
            if (activeLedgerId === null) {
                throw new Error('No active space selected.');
            }

            return updateMcpSettings(activeLedgerId, {
                enabled: values.enabled,
                allow_read: values.allow_read,
                allow_write: values.allow_write,
                allow_destructive: values.allow_destructive,
                post_mode: values.post_mode,
                destructive_ack: values.destructive_ack,
            });
        },
        onSuccess: () => {
            clearErrors('root');
            queryClient.invalidateQueries({ queryKey: ['mcp-settings', activeLedgerId] });
            queryClient.invalidateQueries({ queryKey: ['ledgers'] });
            setSaveSuccess(true);
            setTimeout(() => setSaveSuccess(false), 3000);
        },
        onError: (error: unknown) => {
            setSaveSuccess(false);
            applyApiErrors(error, setError);
        },
    });

    return (
        <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="flex flex-col gap-6"
        >
            {errors.root && (
                <Alert variant="destructive">
                    <AlertTriangleIcon />
                    <AlertDescription>{errors.root.message}</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader className="border-b">
                    <CardTitle className="flex items-center gap-2">
                        <BotIcon />
                        MCP access
                    </CardTitle>
                    <CardDescription>
                        Tools run as you, using the same ledger policies as the app. There are no user-management or space CRUD tools.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup className="flex flex-col gap-4">
                        <Controller
                            control={form.control}
                            name="enabled"
                            render={({ field }) => (
                                <Field orientation="horizontal" className="items-center justify-between">
                                    <FieldLabel htmlFor="mcp-enabled">Enable MCP for this space</FieldLabel>
                                    <Switch
                                        id="mcp-enabled"
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                        disabled={isLoading || mutation.isPending}
                                        aria-label="Enable MCP for this space"
                                    />
                                </Field>
                            )}
                        />
                        <Controller
                            control={form.control}
                            name="allow_read"
                            render={({ field }) => (
                                <Field orientation="horizontal" className="items-center justify-between">
                                    <FieldLabel htmlFor="mcp-read">Allow read operations</FieldLabel>
                                    <Switch
                                        id="mcp-read"
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                        disabled={isLoading || mutation.isPending}
                                        aria-label="Allow read operations"
                                    />
                                </Field>
                            )}
                        />
                        <Controller
                            control={form.control}
                            name="allow_write"
                            render={({ field }) => (
                                <Field orientation="horizontal" className="items-center justify-between">
                                    <FieldLabel htmlFor="mcp-write">Allow write operations</FieldLabel>
                                    <Switch
                                        id="mcp-write"
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                        disabled={isLoading || mutation.isPending}
                                        aria-label="Allow write operations"
                                    />
                                </Field>
                            )}
                        />
                        <Controller
                            control={form.control}
                            name="post_mode"
                            render={({ field, fieldState }) => (
                                <Field>
                                    <FieldLabel>Write posting mode</FieldLabel>
                                    <FieldContent>
                                        <Select
                                            value={field.value}
                                            onValueChange={field.onChange}
                                            disabled={isLoading || mutation.isPending}
                                        >
                                            <SelectTrigger aria-label="Write posting mode">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="approval_queue">Submit to approval queue</SelectItem>
                                                <SelectItem value="direct">Post immediately</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </FieldContent>
                                    <FieldError errors={[fieldState.error]} />
                                </Field>
                            )}
                        />
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="border-b">
                    <CardTitle>Destructive operations</CardTitle>
                    <CardDescription>
                        Deletes, settlement confirmation, and pending approve/reject require an explicit acknowledgement.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup className="flex flex-col gap-4">
                        <Controller
                            control={form.control}
                            name="allow_destructive"
                            render={({ field }) => (
                                <Field orientation="horizontal" className="items-start gap-3">
                                    <Checkbox
                                        id="mcp-destructive"
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                        disabled={(!form.watch('destructive_ack') && !field.value) || isLoading || mutation.isPending}
                                        aria-label="Allow destructive operations"
                                    />
                                    <label htmlFor="mcp-destructive" className="cursor-pointer">
                                        <div className="text-sm font-semibold">Allow destructive MCP operations</div>
                                        <div className="text-sm text-muted-foreground">
                                            Agents may delete transactions/accounts/blueprints, confirm settlements, and review pending proposals.
                                        </div>
                                    </label>
                                </Field>
                            )}
                        />
                        {!form.watch('allow_destructive') && (
                            <Controller
                                control={form.control}
                                name="destructive_ack"
                                render={({ field, fieldState }) => (
                                    <div className="flex flex-col gap-1">
                                        <Field orientation="horizontal" className="items-center gap-2">
                                            <Checkbox
                                                id="mcp-destructive-ack"
                                                checked={field.value}
                                                onCheckedChange={field.onChange}
                                                aria-label="Acknowledge destructive MCP risks"
                                            />
                                            <label htmlFor="mcp-destructive-ack" className="text-xs font-medium cursor-pointer">
                                                I understand and agree to the operational risks.
                                            </label>
                                        </Field>
                                        <FieldError errors={[fieldState.error]} />
                                    </div>
                                )}
                            />
                        )}
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="border-b">
                    <CardTitle>Browser agent</CardTitle>
                    <CardDescription>
                        In-browser WebMCP tools use your session and these same settings. External agents connect to the Laravel MCP server.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <p className="text-sm">
                        Status:{' '}
                        <span className="font-medium">
                            {webMcpSupported ? 'supported in this browser' : 'unsupported in this browser'}
                        </span>
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Action logs are observability only and never replace the pending approval queue.
                    </p>
                    <Button type="button" variant="outline" asChild>
                        <Link to="/settings/mcp-log">View action log</Link>
                    </Button>
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={mutation.isPending || isLoading}>
                    {mutation.isPending ? (
                        <Loader2Icon data-icon="inline-start" className="animate-spin" />
                    ) : saveSuccess ? (
                        <CheckCircle2Icon data-icon="inline-start" />
                    ) : (
                        <SaveIcon data-icon="inline-start" />
                    )}
                    {saveSuccess ? 'Saved!' : 'Save MCP settings'}
                </Button>
            </div>
        </form>
    );
}
