import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { ApiError, AuthResponse } from '@/api/types';
import { useAuthStore } from '@/stores/authStore';

import LoginPage from './LoginPage';

const loginMock = vi.fn();

vi.mock('@/api/auth', () => ({
    login: (payload: { email: string; password: string }) => loginMock(payload),
}));

function createTestQueryClient() {
    return new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });
}

function renderLoginPage() {
    const queryClient = createTestQueryClient();
    return render(
        <QueryClientProvider client={queryClient}>
            <MemoryRouter initialEntries={['/login']}>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/" element={<div>Logged-in home</div>} />
                </Routes>
            </MemoryRouter>
        </QueryClientProvider>,
    );
}

const successResponse: AuthResponse = {
    user: { id: 1, name: 'Test User', email: 'test@example.com' },
    token: 'test-token',
};

describe('LoginPage', () => {
    beforeEach(() => {
        loginMock.mockReset();
        localStorage.removeItem('poruko-auth');
        useAuthStore.setState({ user: null, token: null });
    });

    afterEach(() => {
        loginMock.mockReset();
        localStorage.removeItem('poruko-auth');
        useAuthStore.setState({ user: null, token: null });
    });

    it('renders sign-in copy and register link', () => {
        renderLoginPage();

        expect(screen.getByText('Welcome back')).toBeInTheDocument();
        expect(screen.getByText(/sign in to your poruko account/i)).toBeInTheDocument();
        const registerLink = screen.getByRole('link', { name: /^register$/i });
        expect(registerLink).toHaveAttribute('href', '/register');
    });

    it('shows validation errors when fields are empty', async () => {
        const user = userEvent.setup();
        renderLoginPage();

        await user.click(screen.getByRole('button', { name: /sign in$/i }));

        expect(await screen.findByText('Enter a valid email address')).toBeInTheDocument();
        expect(screen.getByText('Password is required')).toBeInTheDocument();
        expect(loginMock).not.toHaveBeenCalled();
    });

    it('shows validation error for an invalid email', async () => {
        const user = userEvent.setup();
        renderLoginPage();

        const emailInput = screen.getByLabelText(/^email$/i);
        await user.type(emailInput, 'not-an-email');
        await user.type(screen.getByLabelText(/^password$/i), 'secret');
        // Native `type="email"` constraint blocks click-submit before RHF+Zod run.
        const form = emailInput.closest('form');
        expect(form).toBeTruthy();
        fireEvent.submit(form!);

        expect(await screen.findByText('Enter a valid email address')).toBeInTheDocument();
        expect(loginMock).not.toHaveBeenCalled();
    });

    it('submits credentials, stores auth, and navigates home on success', async () => {
        const user = userEvent.setup();
        loginMock.mockResolvedValueOnce(successResponse);

        renderLoginPage();

        await user.type(screen.getByLabelText(/^email$/i), 'hello@example.com');
        await user.type(screen.getByLabelText(/^password$/i), 'password123');
        await user.click(screen.getByRole('button', { name: /sign in$/i }));

        await waitFor(() => {
            expect(loginMock).toHaveBeenCalledWith({
                email: 'hello@example.com',
                password: 'password123',
            });
        });

        await waitFor(() => {
            expect(screen.getByText('Logged-in home')).toBeInTheDocument();
        });

        expect(useAuthStore.getState().token).toBe('test-token');
        expect(useAuthStore.getState().user).toEqual(successResponse.user);
    });

    it('shows the API error message when login fails with a message', async () => {
        const user = userEvent.setup();
        const error = {
            response: { data: { message: 'Invalid credentials' } },
        } as AxiosError<ApiError>;
        loginMock.mockRejectedValueOnce(error);

        renderLoginPage();

        await user.type(screen.getByLabelText(/^email$/i), 'a@b.co');
        await user.type(screen.getByLabelText(/^password$/i), 'wrong');
        await user.click(screen.getByRole('button', { name: /sign in$/i }));

        expect(await screen.findByText('Invalid credentials')).toBeInTheDocument();
    });

    it('shows a generic message when login fails without a message', async () => {
        const user = userEvent.setup();
        loginMock.mockRejectedValueOnce(new Error('network'));

        renderLoginPage();

        await user.type(screen.getByLabelText(/^email$/i), 'a@b.co');
        await user.type(screen.getByLabelText(/^password$/i), 'wrong');
        await user.click(screen.getByRole('button', { name: /sign in$/i }));

        expect(await screen.findByText('Login failed. Please try again.')).toBeInTheDocument();
    });

    it('shows signing-in state while the request is in flight', async () => {
        const user = userEvent.setup();
        let resolveLogin!: (value: AuthResponse) => void;
        const loginPromise = new Promise<AuthResponse>((resolve) => {
            resolveLogin = resolve;
        });
        loginMock.mockReturnValueOnce(loginPromise);

        renderLoginPage();

        await user.type(screen.getByLabelText(/^email$/i), 'hello@example.com');
        await user.type(screen.getByLabelText(/^password$/i), 'password123');
        await user.click(screen.getByRole('button', { name: /sign in$/i }));

        expect(await screen.findByRole('button', { name: /signing in/i })).toBeDisabled();

        resolveLogin(successResponse);

        await waitFor(() => {
            expect(screen.getByText('Logged-in home')).toBeInTheDocument();
        });
    });
});
