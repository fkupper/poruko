import { create } from 'zustand';
import { persist } from 'zustand/middleware';

import type { User } from '@/api/types';
import { queryClient } from '@/lib/queryClient';
import { useLedgerStore } from '@/stores/ledgerStore';

interface AuthState {
    user: User | null;
    token: string | null;
    setAuth: (user: User, token: string) => void;
    setUser: (user: User) => void;
    logout: () => void;
}

export const useAuthStore = create<AuthState>()(
    persist(
        (set) => ({
            user: null,
            token: null,
            setAuth: (user, token) => {
                useLedgerStore.getState().setActiveLedgerId(null);
                try {
                    localStorage.removeItem('poruko-ledger-storage');
                } catch {
                    // Ignore storage errors
                }
                queryClient.clear();
                set({ user, token });
            },
            setUser: (user) => set({ user }),
            logout: () => {
                useLedgerStore.getState().setActiveLedgerId(null);
                try {
                    localStorage.removeItem('poruko-ledger-storage');
                } catch {
                    // Ignore storage errors
                }
                queryClient.clear();
                set({ user: null, token: null });
            },
        }),
        {
            name: 'poruko-auth',
            // Only persist the token; user is re-fetched on boot via /auth/me
            partialize: (state) => ({ token: state.token }),
        },
    ),
);
