import { Link, useNavigate } from 'react-router-dom';
import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import type { AxiosError } from 'axios';

import { login, challengeTwoFactor } from '@/api/auth';
import type { ApiError } from '@/api/types';
import { useAuthStore } from '@/stores/authStore';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

const schema = z.object({
    email: z.string().email('Enter a valid email address'),
    password: z.string().min(1, 'Password is required'),
});

type FormValues = z.infer<typeof schema>;

export default function LoginPage() {
    const navigate = useNavigate();
    const setAuth = useAuthStore((s) => s.setAuth);
    const setPendingTwoFactorToken = useAuthStore((s) => s.setPendingTwoFactorToken);
    const [requiresTwoFactor, setRequiresTwoFactor] = React.useState(false);
    const [twoFactorCode, setTwoFactorCode] = React.useState('');
    const [useRecoveryCode, setUseRecoveryCode] = React.useState(false);

    const {
        register,
        handleSubmit,
        setError,
        clearErrors,
        formState: { errors, isSubmitting },
    } = useForm<FormValues>({
        resolver: zodResolver(schema),
        defaultValues: { email: '', password: '' },
    });

    const resetTwoFactorChallenge = React.useCallback(() => {
        setRequiresTwoFactor(false);
        setTwoFactorCode('');
        setUseRecoveryCode(false);
        setPendingTwoFactorToken(null);
        clearErrors('root');
    }, [clearErrors, setPendingTwoFactorToken]);

    const mutation = useMutation({
        mutationFn: login,
        onSuccess: (data) => {
            if (data.two_factor) {
                // Keep challenge UI on /login — do not promote limited token to full session
                setPendingTwoFactorToken(data.token);
                setRequiresTwoFactor(true);
                return;
            }
            setAuth(data.user, data.token);
            navigate('/');
        },
        onError: (error: AxiosError<ApiError>) => {
            const message = error.response?.data?.message ?? 'Login failed. Please try again.';
            setError('root', { message });
        },
    });

    const challengeMutation = useMutation({
        mutationFn: () => {
            const payload = useRecoveryCode
                ? { recovery_code: twoFactorCode }
                : { code: twoFactorCode };
            return challengeTwoFactor(payload);
        },
        onSuccess: ({ user, token }) => {
            setAuth(user, token);
            navigate('/');
        },
        onError: (error: AxiosError<ApiError>) => {
            if (error.response?.status === 401) {
                resetTwoFactorChallenge();
                setError('root', { message: 'Session expired. Please sign in again.' });
                return;
            }
            const message = error.response?.data?.message ?? 'Invalid code. Please try again.';
            setError('root', { message });
        },
    });

    return (
        <div className="flex min-h-svh items-center justify-center p-4">
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>{requiresTwoFactor ? 'Two-Factor Authentication' : 'Welcome back'}</CardTitle>
                    <CardDescription>{requiresTwoFactor ? 'Enter your authenticator code to continue' : 'Sign in to your Poruko account'}</CardDescription>
                </CardHeader>
                {requiresTwoFactor ? (
                    <form onSubmit={(e) => { e.preventDefault(); challengeMutation.mutate(); }}>
                        <CardContent>
                            <FieldGroup>
                                {errors.root && (
                                    <Alert variant="destructive">
                                        <AlertDescription>{errors.root.message}</AlertDescription>
                                    </Alert>
                                )}
                                <Field>
                                    <FieldLabel htmlFor="code">{useRecoveryCode ? 'Recovery Code' : 'Authentication Code'}</FieldLabel>
                                    <Input
                                        id="code"
                                        type="text"
                                        placeholder={useRecoveryCode ? 'e.g. 1a2b-3c4d' : '6-digit code'}
                                        value={twoFactorCode}
                                        onChange={(e) => setTwoFactorCode(e.target.value)}
                                        autoComplete="one-time-code"
                                        className="font-mono"
                                    />
                                </Field>
                            </FieldGroup>
                            <button
                                type="button"
                                onClick={() => {
                                    setUseRecoveryCode(!useRecoveryCode);
                                    setTwoFactorCode('');
                                }}
                                className="text-sm text-muted-foreground hover:text-foreground mt-4 underline underline-offset-4"
                            >
                                {useRecoveryCode ? 'Use authentication code instead' : 'Use a recovery code'}
                            </button>
                        </CardContent>
                        <CardFooter className="flex flex-col gap-3">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={!twoFactorCode || challengeMutation.isPending}
                            >
                                {challengeMutation.isPending && <Spinner data-icon="inline-start" />}
                                {challengeMutation.isPending ? 'Verifying…' : 'Verify'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                onClick={resetTwoFactorChallenge}
                            >
                                Cancel
                            </Button>
                        </CardFooter>
                    </form>
                ) : (
                    <form onSubmit={handleSubmit((values) => mutation.mutate(values))}>
                        <CardContent>
                            <FieldGroup>
                                {errors.root && (
                                    <Alert variant="destructive">
                                        <AlertDescription>{errors.root.message}</AlertDescription>
                                    </Alert>
                                )}
                                <Field data-invalid={!!errors.email || undefined}>
                                    <FieldLabel htmlFor="email">Email</FieldLabel>
                                    <Input
                                        id="email"
                                        type="email"
                                        placeholder="you@example.com"
                                        aria-invalid={!!errors.email}
                                        {...register('email')}
                                    />
                                    {errors.email && <FieldError>{errors.email.message}</FieldError>}
                                </Field>
                                <Field data-invalid={!!errors.password || undefined}>
                                    <FieldLabel htmlFor="password">Password</FieldLabel>
                                    <Input
                                        id="password"
                                        type="password"
                                        placeholder="••••••••"
                                        aria-invalid={!!errors.password}
                                        {...register('password')}
                                    />
                                    {errors.password && (
                                        <FieldError>{errors.password.message}</FieldError>
                                    )}
                                </Field>
                            </FieldGroup>
                        </CardContent>
                        <CardFooter className="flex flex-col gap-3">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={isSubmitting || mutation.isPending}
                            >
                                {mutation.isPending && <Spinner data-icon="inline-start" />}
                                {mutation.isPending ? 'Signing in…' : 'Sign in'}
                            </Button>
                            <p className="text-sm text-muted-foreground">
                                {"Don't have an account? "}
                                <Link to="/register" className="text-foreground underline underline-offset-4">
                                    Register
                                </Link>
                            </p>
                        </CardFooter>
                    </form>
                )}
            </Card>
        </div>
    );
}
