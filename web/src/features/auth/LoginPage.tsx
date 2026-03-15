import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { useAuthStore } from '../../stores/authStore';
import { SectionBlock } from '../../components/SectionBlock';

const loginSchema = z.object({
  email: z.string().email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export function LoginPage() {
  const navigate = useNavigate();
  const login = useAuthStore((state) => state.login);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: '',
      password: '',
    },
  });

  async function onSubmit(values: LoginFormValues): Promise<void> {
    setErrorMessage(null);

    try {
      await login(values);
      navigate('/', { replace: true });
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Login failed');
    }
  }

  return (
    <main className="mx-auto grid min-h-[70vh] w-full max-w-md place-items-center px-4 py-8">
      <SectionBlock
        title="Log in"
        subtitle="Access your Poruko account."
        className="w-full"
      >
        <form className="grid gap-3" onSubmit={form.handleSubmit(onSubmit)}>
          <label className="grid gap-1 text-sm">
            <span>Email</span>
            <input className="field-input" type="email" {...form.register('email')} />
          </label>
          {form.formState.errors.email && (
            <p className="text-sm text-destructive">{form.formState.errors.email.message}</p>
          )}

          <label className="grid gap-1 text-sm">
            <span>Password</span>
            <input className="field-input" type="password" {...form.register('password')} />
          </label>
          {form.formState.errors.password && (
            <p className="text-sm text-destructive">{form.formState.errors.password.message}</p>
          )}

          {errorMessage && <p className="text-sm text-destructive">{errorMessage}</p>}

          <button
            className="btn-primary"
            disabled={form.formState.isSubmitting}
            type="submit"
          >
            {form.formState.isSubmitting ? 'Signing in...' : 'Log in'}
          </button>
        </form>

        <p className="mt-4 text-sm text-muted-foreground">
          Need an account?{' '}
          <Link className="text-info hover:opacity-80" to="/register">
            Register
          </Link>
        </p>
      </SectionBlock>
    </main>
  );
}
