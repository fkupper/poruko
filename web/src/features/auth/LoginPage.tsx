import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { useAuthStore } from '../../stores/authStore';

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
      navigate('/ledgers/1/accounts', { replace: true });
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Login failed');
    }
  }

  return (
    <main className="mx-auto grid min-h-[70vh] w-full max-w-md place-items-center px-4 py-8">
      <section className="w-full rounded-xl border border-slate-800 bg-slate-900 p-4">
        <h1 className="text-2xl font-semibold">Log in</h1>
        <p className="mt-1 text-sm text-slate-400">Access your Poruko account.</p>

        <form className="mt-4 grid gap-3" onSubmit={form.handleSubmit(onSubmit)}>
          <input
            className="rounded border border-slate-700 bg-slate-950 px-3 py-2"
            placeholder="Email"
            type="email"
            {...form.register('email')}
          />
          {form.formState.errors.email && (
            <p className="text-sm text-red-400">{form.formState.errors.email.message}</p>
          )}

          <input
            className="rounded border border-slate-700 bg-slate-950 px-3 py-2"
            placeholder="Password"
            type="password"
            {...form.register('password')}
          />
          {form.formState.errors.password && (
            <p className="text-sm text-red-400">{form.formState.errors.password.message}</p>
          )}

          {errorMessage && <p className="text-sm text-red-400">{errorMessage}</p>}

          <button
            className="rounded bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
            disabled={form.formState.isSubmitting}
            type="submit"
          >
            {form.formState.isSubmitting ? 'Signing in...' : 'Log in'}
          </button>
        </form>

        <p className="mt-4 text-sm text-slate-400">
          Need an account?{' '}
          <Link className="text-indigo-400 hover:text-indigo-300" to="/register">
            Register
          </Link>
        </p>
      </section>
    </main>
  );
}
