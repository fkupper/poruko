import { create } from 'zustand';
import type { LoginInput, RegisterInput } from '../api/auth';
import { fetchMe, login, logout, register, type AuthUser } from '../api/auth';
import { ACCESS_TOKEN_STORAGE_KEY } from '../api/client';

interface AuthState {
  user: AuthUser | null;
  token: string | null;
  isBootstrapping: boolean;
  hydrateToken: () => void;
  register: (payload: RegisterInput) => Promise<void>;
  login: (payload: LoginInput) => Promise<void>;
  fetchMe: () => Promise<void>;
  logout: () => Promise<void>;
}

function persistToken(token: string | null): void {
  if (token === null) {
    localStorage.removeItem(ACCESS_TOKEN_STORAGE_KEY);
    return;
  }

  localStorage.setItem(ACCESS_TOKEN_STORAGE_KEY, token);
}

export const useAuthStore = create<AuthState>()((set, get) => ({
  user: null,
  token: null,
  isBootstrapping: true,
  hydrateToken: () => {
    set({
      token: localStorage.getItem(ACCESS_TOKEN_STORAGE_KEY),
    });
  },
  register: async (payload) => {
    const response = await register(payload);
    persistToken(response.token);

    set({
      user: response.user,
      token: response.token,
      isBootstrapping: false,
    });
  },
  login: async (payload) => {
    const response = await login(payload);
    persistToken(response.token);

    set({
      user: response.user,
      token: response.token,
      isBootstrapping: false,
    });
  },
  fetchMe: async () => {
    const token = get().token;

    if (token === null) {
      set({
        user: null,
        isBootstrapping: false,
      });
      return;
    }

    try {
      const response = await fetchMe(token);
      set({
        user: response.user,
        isBootstrapping: false,
      });
    } catch {
      persistToken(null);
      set({
        user: null,
        token: null,
        isBootstrapping: false,
      });
    }
  },
  logout: async () => {
    const token = get().token;

    if (token !== null) {
      try {
        await logout(token);
      } catch (error) {
        void error;
      }
    }

    persistToken(null);
    set({
      user: null,
      token: null,
      isBootstrapping: false,
    });
  },
}));
