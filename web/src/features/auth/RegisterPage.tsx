import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { useAuthStore } from '../../stores/authStore';

const registerSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters'),
  email: z.string().email('Enter a valid email address'),
  password: z.string().min(8, 'Password must be at least 8 characters'),
});

type RegisterFormValues = z.infer<typeof registerSchema>;

export function RegisterPage() {
  const navigate = useNavigate();
  const register = useAuthStore((state) => state.register);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const form = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      name: '',
      email: '',
      password: '',
    },
  });

  async function onSubmit(values: RegisterFormValues): Promise<void> {
    setErrorMessage(null);

    try {
      await register(values);
      navigate('/ledgers/1/accounts', { replace: true });
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Registration failed');
    }
  }

  return (
    <main className="mx-auto grid min-h-[70vh] w-full max-w-md place-items-center px-4 py-8">
      <section className="panel w-full">
        <h1 className="text-2xl font-semibold">Create account</h1>
        <p className="mt-1 text-sm text-muted-foreground">Start using Poruko in seconds.</p>

        <form className="mt-4 grid gap-3" onSubmit={form.handleSubmit(onSubmit)}>
          <input
            className="field-input"
            placeholder="Full name"
            type="text"
            {...form.register('name')}
          />
          {form.formState.errors.name && (
            <p className="text-sm text-destructive">{form.formState.errors.name.message}</p>
          )}

          <input
            className="field-input"
            placeholder="Email"
            type="email"
            {...form.register('email')}
          />
          {form.formState.errors.email && (
            <p className="text-sm text-destructive">{form.formState.errors.email.message}</p>
          )}

          <input
            className="field-input"
            placeholder="Password"
            type="password"
            {...form.register('password')}
          />
          {form.formState.errors.password && (
            <p className="text-sm text-destructive">{form.formState.errors.password.message}</p>
          )}

          {errorMessage && <p className="text-sm text-destructive">{errorMessage}</p>}

          <button
            className="btn-primary"
            disabled={form.formState.isSubmitting}
            type="submit"
          >
            {form.formState.isSubmitting ? 'Creating...' : 'Register'}
          </button>
        </form>

        <p className="mt-4 text-sm text-muted-foreground">
          Already have an account?{' '}
          <Link className="text-info hover:opacity-80" to="/login">
            Log in
          </Link>
        </p>
      </section>
    </main>
  );
}
