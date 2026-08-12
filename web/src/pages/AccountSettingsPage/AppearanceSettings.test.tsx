import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/authStore';
import { useThemeStore } from '@/stores/themeStore';

import { AppearanceSettings } from './AppearanceSettings';

const updateAppearanceMock = vi.fn();

vi.mock('@/api/auth', () => ({
    updateAppearance: (payload: { theme: string; color_mode: string }) => updateAppearanceMock(payload),
}));

function renderAppearance() {
    const onFeedback = vi.fn();
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });

    render(
        <QueryClientProvider client={queryClient}>
            <AppearanceSettings onFeedback={onFeedback} />
        </QueryClientProvider>,
    );

    return { onFeedback };
}

describe('AppearanceSettings', () => {
    beforeEach(() => {
        updateAppearanceMock.mockReset();
        localStorage.clear();
        useThemeStore.setState({ theme: 'poruko', colorMode: 'system' });
        useAuthStore.setState({
            user: {
                id: 1,
                name: 'Test',
                email: 'test@example.com',
                theme: 'poruko',
                color_mode: 'system',
            },
            token: 'test-token',
            pendingTwoFactorToken: null,
            authStatus: 'ready',
        });
        updateAppearanceMock.mockResolvedValue({
            user: {
                id: 1,
                name: 'Test',
                email: 'test@example.com',
                theme: 'quiet',
                color_mode: 'dark',
            },
        });
    });

    afterEach(() => {
        cleanup();
        localStorage.clear();
    });

    it('renders theme and color mode controls', () => {
        renderAppearance();

        expect(screen.getByText('Appearance')).toBeInTheDocument();
        expect(screen.getByLabelText('Light')).toBeInTheDocument();
        expect(screen.getByLabelText('Dark')).toBeInTheDocument();
        expect(screen.getByLabelText('System')).toBeInTheDocument();
        expect(screen.getByLabelText('Poruko')).toBeInTheDocument();
        expect(screen.getByLabelText('Neutral')).toBeInTheDocument();
        expect(screen.getByLabelText('Quiet')).toBeInTheDocument();
    });

    it('persists appearance changes via the API', async () => {
        const user = userEvent.setup();
        const { onFeedback } = renderAppearance();

        await user.click(screen.getByLabelText('Quiet'));

        await waitFor(() => {
            expect(updateAppearanceMock).toHaveBeenCalledWith({
                theme: 'quiet',
                color_mode: 'system',
            });
        });

        await waitFor(() => {
            expect(onFeedback).toHaveBeenCalledWith('success', 'Appearance saved.');
        });

        expect(useThemeStore.getState().theme).toBe('quiet');
    });
});
