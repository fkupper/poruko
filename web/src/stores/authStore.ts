import { create } from 'zustand';
import { persist } from 'zustand/middleware';

import type { User } from '@/api/types';

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
            setAuth: (user, token) => set({ user, token }),
            setUser: (user) => set({ user }),
            logout: () => set({ user: null, token: null }),
        }),
        {
            name: 'poruko-auth',
            // Only persist the token; user is re-fetched on boot via /auth/me
            partialize: (state) => ({ token: state.token }),
        },
    ),
);
