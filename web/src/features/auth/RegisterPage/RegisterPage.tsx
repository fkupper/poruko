import { Link, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import type { AxiosError } from 'axios';

import { register as registerApi } from '@/api/auth';
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
    name: z.string().min(1, 'Name is required'),
    email: z.string().email('Enter a valid email address'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    passwordConfirmation: z.string(),
}).refine((data) => data.password === data.passwordConfirmation, {
    message: 'Passwords do not match',
    path: ['passwordConfirmation'],
});

type FormValues = z.infer<typeof schema>;

export default function RegisterPage() {
    const navigate = useNavigate();
    const setAuth = useAuthStore((s) => s.setAuth);

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors, isSubmitting },
    } = useForm<FormValues>({
        resolver: zodResolver(schema),
        defaultValues: { name: '', email: '', password: '', passwordConfirmation: '' },
    });

    const mutation = useMutation({
        mutationFn: (values: Omit<FormValues, 'passwordConfirmation'>) => registerApi(values),
        onSuccess: ({ user, token }) => {
            setAuth(user, token);
            navigate('/');
        },
        onError: (error: AxiosError<ApiError>) => {
            const message = error.response?.data?.message ?? 'Registration failed. Please try again.';
            setError('root', { message });
        },
    });

    return (
        <div className="flex min-h-svh items-center justify-center p-4">
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Create an account</CardTitle>
                    <CardDescription>Join Poruko to manage your shared finances</CardDescription>
                </CardHeader>
                <form
                    onSubmit={handleSubmit(({ passwordConfirmation, ...values }) => {
                        void passwordConfirmation;
                        mutation.mutate(values);
                    })}
                >
                    <CardContent>
                        <FieldGroup>
                            {errors.root && (
                                <Alert variant="destructive">
                                    <AlertDescription>{errors.root.message}</AlertDescription>
                                </Alert>
                            )}
                            <Field data-invalid={!!errors.name || undefined}>
                                <FieldLabel htmlFor="name">Full name</FieldLabel>
                                <Input
                                    id="name"
                                    type="text"
                                    placeholder="Alice Example"
                                    aria-invalid={!!errors.name}
                                    {...register('name')}
                                />
                                {errors.name && <FieldError>{errors.name.message}</FieldError>}
                            </Field>
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
                            <Field data-invalid={!!errors.passwordConfirmation || undefined}>
                                <FieldLabel htmlFor="passwordConfirmation">Confirm password</FieldLabel>
                                <Input
                                    id="passwordConfirmation"
                                    type="password"
                                    placeholder="••••••••"
                                    aria-invalid={!!errors.passwordConfirmation}
                                    {...register('passwordConfirmation')}
                                />
                                {errors.passwordConfirmation && (
                                    <FieldError>
                                        {errors.passwordConfirmation.message}
                                    </FieldError>
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
                            {mutation.isPending ? 'Creating account…' : 'Create account'}
                        </Button>
                        <p className="text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <Link to="/login" className="text-foreground underline underline-offset-4">
                                Sign in
                            </Link>
                        </p>
                    </CardFooter>
                </form>
            </Card>
        </div>
    );
}
