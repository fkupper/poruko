import { create } from 'zustand';
import { persist } from 'zustand/middleware';

import type { User } from '@/api/types';
import { queryClient } from '@/lib/queryClient';
import { useLedgerStore } from '@/stores/ledgerStore';
import { useThemeStore } from '@/stores/themeStore';

export type AuthStatus = 'anonymous' | 'hydrating' | 'ready';

interface AuthState {
    user: User | null;
    token: string | null;
    /** Temporary token issued when login requires 2FA. Not persisted. */
    pendingTwoFactorToken: string | null;
    /** Session readiness for gating the protected shell after reload. */
    authStatus: AuthStatus;
    setAuth: (user: User, token: string) => void;
    setUser: (user: User) => void;
    setPendingTwoFactorToken: (token: string | null) => void;
    setAuthStatus: (status: AuthStatus) => void;
    logout: () => void;
}

function clearLedgerSession(): void {
    useLedgerStore.getState().setActiveLedgerId(null);
    try {
        localStorage.removeItem('poruko-ledger-storage');
    } catch {
        // Ignore storage errors
    }
    queryClient.clear();
}

export const useAuthStore = create<AuthState>()(
    persist(
        (set) => ({
            user: null,
            token: null,
            pendingTwoFactorToken: null,
            authStatus: 'anonymous',
            setAuth: (user, token) => {
                clearLedgerSession();
                set({ user, token, pendingTwoFactorToken: null, authStatus: 'ready' });
                useThemeStore.getState().hydrateFromUser(user);
            },
            setUser: (user) => {
                set({ user, authStatus: 'ready' });
                useThemeStore.getState().hydrateFromUser(user);
            },
            setPendingTwoFactorToken: (token) => set({ pendingTwoFactorToken: token }),
            setAuthStatus: (status) => set({ authStatus: status }),
            logout: () => {
                clearLedgerSession();
                set({ user: null, token: null, pendingTwoFactorToken: null, authStatus: 'anonymous' });
            },
        }),
        {
            name: 'poruko-auth',
            // Only persist the full session token; pending 2FA token must not survive reloads
            partialize: (state) => ({ token: state.token }),
            onRehydrateStorage: () => (state) => {
                // After persist rehydrate: if token exists, mark hydrating until /auth/me completes
                if (state?.token) {
                    state.authStatus = 'hydrating';
                } else if (state) {
                    state.authStatus = 'anonymous';
                }
            },
        },
    ),
);
