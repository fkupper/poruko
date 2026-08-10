import axios from 'axios';

import { useAuthStore } from '@/stores/authStore';

const client = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
});

function getErrorMessage(error: unknown): string | undefined {
    if (!axios.isAxiosError(error)) return undefined;
    const data = error.response?.data;
    if (data && typeof data === 'object' && 'message' in data && typeof data.message === 'string') {
        return data.message;
    }
    return undefined;
}

client.interceptors.request.use((config) => {
    const { token, pendingTwoFactorToken } = useAuthStore.getState();
    const authToken = token ?? pendingTwoFactorToken;
    if (authToken) {
        config.headers.Authorization = `Bearer ${authToken}`;
    }
    return config;
});

client.interceptors.response.use(
    (response) => response,
    (error: unknown) => {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined;
        const url = axios.isAxiosError(error) ? (error.config?.url ?? '') : '';

        // Only redirect on 401 for authenticated requests, not for login/register attempts.
        const isAuthEndpoint = url.startsWith('/auth/');
        if (status === 401 && !isAuthEndpoint) {
            useAuthStore.getState().logout();
            window.location.href = '/login';
        }

        // Redirect to /account if 2FA is enforced but not set up
        const message = getErrorMessage(error);
        if (status === 403 && message === 'Two-factor authentication must be enabled.') {
            if (window.location.pathname !== '/account') {
                window.location.href = '/account';
            }
        }

        return Promise.reject(error);
    },
);

export default client;
