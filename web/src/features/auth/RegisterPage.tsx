import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { useAuthStore } from '../../stores/authStore';
import { SectionBlock } from '../../components/SectionBlock';

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
      navigate('/', { replace: true });
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Registration failed');
    }
  }

  return (
    <main className="mx-auto grid min-h-[70vh] w-full max-w-md place-items-center px-4 py-8">
      <SectionBlock
        title="Create account"
        subtitle="Start using Poruko in seconds."
        className="w-full"
      >
        <form className="grid gap-3" onSubmit={form.handleSubmit(onSubmit)}>
          <label className="grid gap-1 text-sm">
            <span>Full name</span>
            <input className="field-input" type="text" {...form.register('name')} />
          </label>
          {form.formState.errors.name && (
            <p className="text-sm text-destructive">{form.formState.errors.name.message}</p>
          )}

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
            {form.formState.isSubmitting ? 'Creating...' : 'Register'}
          </button>
        </form>

        <p className="mt-4 text-sm text-muted-foreground">
          Already have an account?{' '}
          <Link className="text-info hover:opacity-80" to="/login">
            Log in
          </Link>
        </p>
      </SectionBlock>
    </main>
  );
}
