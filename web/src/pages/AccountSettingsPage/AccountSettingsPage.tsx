import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import client from '@/api/client';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ShieldCheckIcon, ShieldAlertIcon, Loader2Icon, CopyIcon } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
export default function AccountSettingsPage() {
    const queryClient = useQueryClient();
    
    const [qrCodeSvg, setQrCodeSvg] = React.useState<string | null>(null);
    const [secretKey, setSecretKey] = React.useState<string | null>(null);
    const [verificationCode, setVerificationCode] = React.useState('');
    const [isConfirming, setIsConfirming] = React.useState(false);
    const [feedback, setFeedback] = React.useState<{ type: 'success' | 'error'; message: string } | null>(null);

    const showFeedback = (type: 'success' | 'error', message: string) => {
        setFeedback({ type, message });
        setTimeout(() => setFeedback(null), 5000);
    };

    // The backend `two_factor_secret` is not exposed in the User resource by default, 
    // but if the user has 2FA enabled, they will have `two_factor_confirmed_at` (we might need to check this).
    // For now, let's try to fetch recovery codes. If it succeeds, they have it enabled.
    const { data: codes, isLoading: isLoadingCodes, refetch: refetchCodes } = useQuery({
        queryKey: ['two-factor-recovery-codes'],
        queryFn: async () => {
            const { data } = await client.get('/auth/user/two-factor-recovery-codes');
            return data as string[];
        },
        retry: false,
    });

    const is2faEnabled = !!codes && codes.length > 0;

    const enableMutation = useMutation({
        mutationFn: async () => {
            await client.post('/auth/user/two-factor-authentication');
            const [qrRes, secretRes] = await Promise.all([
                client.get('/auth/user/two-factor-qr-code'),
                client.get('/auth/user/two-factor-secret-key'),
            ]);
            return {
                svg: qrRes.data.svg,
                secretKey: secretRes.data.secretKey,
            };
        },
        onSuccess: (data) => {
            setQrCodeSvg(data.svg);
            setSecretKey(data.secretKey);
            setIsConfirming(true);
        },
        onError: () => showFeedback('error', 'Failed to initiate 2FA setup.')
    });

    const confirmMutation = useMutation({
        mutationFn: async (code: string) => {
            await client.post('/auth/user/confirmed-two-factor-authentication', { code });
        },
        onSuccess: () => {
            showFeedback('success', 'Two-factor authentication enabled successfully!');
            setIsConfirming(false);
            setQrCodeSvg(null);
            setSecretKey(null);
            setVerificationCode('');
            refetchCodes();
        },
        onError: () => showFeedback('error', 'Invalid verification code.')
    });

    const disableMutation = useMutation({
        mutationFn: async () => {
            await client.delete('/auth/user/two-factor-authentication');
        },
        onSuccess: () => {
            showFeedback('success', 'Two-factor authentication disabled.');
            queryClient.setQueryData(['two-factor-recovery-codes'], null);
        },
        onError: () => showFeedback('error', 'Failed to disable 2FA.')
    });

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        showFeedback('success', 'Copied to clipboard');
    };

    return (
        <div className="space-y-6 w-full max-w-3xl mx-auto p-4 md:p-8">
            <div>
                <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                    Account Settings
                </h1>
                <p className="text-sm text-muted-foreground">
                    Manage your personal account settings and security.
                </p>
            </div>

            {feedback && (
                <Alert variant={feedback.type === 'error' ? 'destructive' : 'default'} className={feedback.type === 'success' ? 'border-emerald-500 text-emerald-600' : ''}>
                    <AlertDescription>{feedback.message}</AlertDescription>
                </Alert>
            )}

            <Card className="bg-surface border-border">
                <CardHeader className="pb-3 border-b border-border mb-4">
                    <CardTitle className="text-lg font-bold text-primary flex items-center gap-2">
                        {is2faEnabled ? <ShieldCheckIcon className="size-5 text-emerald-500" /> : <ShieldAlertIcon className="size-5 text-amber-500" />}
                        Two-Factor Authentication (2FA)
                    </CardTitle>
                    <CardDescription>
                        Add additional security to your account using two-factor authentication.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    {isLoadingCodes ? (
                        <div className="flex justify-center p-4"><Loader2Icon className="size-6 animate-spin" /></div>
                    ) : is2faEnabled ? (
                        <div className="space-y-4">
                            <p className="text-sm text-foreground">
                                You have enabled two-factor authentication.
                            </p>
                            <div className="bg-secondary/30 p-4 rounded-lg space-y-2">
                                <h3 className="font-semibold text-sm">Recovery Codes</h3>
                                <p className="text-xs text-muted-foreground mb-2">
                                    Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two-factor authentication device is lost.
                                </p>
                                <div className="grid grid-cols-2 gap-2 text-sm font-mono bg-background p-4 rounded border">
                                    {codes.map(code => (
                                        <div key={code}>{code}</div>
                                    ))}
                                </div>
                            </div>
                            <Button 
                                variant="destructive" 
                                onClick={() => disableMutation.mutate()}
                                disabled={disableMutation.isPending}
                            >
                                {disableMutation.isPending ? <Loader2Icon className="size-4 animate-spin mr-2" /> : null}
                                Disable 2FA
                            </Button>
                        </div>
                    ) : isConfirming ? (
                        <div className="space-y-6">
                            <p className="text-sm text-foreground font-medium">
                                To finish enabling two-factor authentication, scan the following QR code using your phone's authenticator application or enter the setup key and provide the generated OTP code.
                            </p>
                            
                            {qrCodeSvg && (
                                <div className="bg-white p-4 inline-block rounded-xl shadow-sm" dangerouslySetInnerHTML={{ __html: qrCodeSvg }} />
                            )}
                            
                            {secretKey && (
                                <div className="space-y-1">
                                    <p className="text-xs font-semibold text-muted-foreground uppercase">Setup Key</p>
                                    <div className="flex items-center gap-2 w-full max-w-sm">
                                        <div className="flex-1 bg-secondary text-secondary-foreground text-sm p-2 rounded font-mono truncate">
                                            {secretKey}
                                        </div>
                                        <Button size="icon" variant="outline" onClick={() => copyToClipboard(secretKey)}>
                                            <CopyIcon className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}

                            <div className="space-y-2 max-w-sm">
                                <label className="text-sm font-medium">Verification Code</label>
                                <Input 
                                    value={verificationCode} 
                                    onChange={e => setVerificationCode(e.target.value)}
                                    placeholder="Enter 6-digit code"
                                    className="font-mono"
                                    maxLength={6}
                                />
                                <Button 
                                    className="w-full mt-2" 
                                    onClick={() => confirmMutation.mutate(verificationCode)}
                                    disabled={verificationCode.length < 6 || confirmMutation.isPending}
                                >
                                    {confirmMutation.isPending ? <Loader2Icon className="size-4 animate-spin mr-2" /> : null}
                                    Confirm & Enable
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                When two-factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone's Google Authenticator application.
                            </p>
                            <Button 
                                onClick={() => enableMutation.mutate()}
                                disabled={enableMutation.isPending}
                            >
                                {enableMutation.isPending ? <Loader2Icon className="size-4 animate-spin mr-2" /> : null}
                                Enable 2FA
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
