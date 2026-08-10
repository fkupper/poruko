import axios from 'axios';

import { useAuthStore } from '@/stores/authStore';

const client = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
});

client.interceptors.request.use((config) => {
    const token = useAuthStore.getState().token;
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

client.interceptors.response.use(
    (response) => response,
    (error: unknown) => {
        const axiosError = error as { response?: { status?: number }; config?: { url?: string } };
        const status = axiosError.response?.status;
        const url = axiosError.config?.url ?? '';

        // Only redirect on 401 for authenticated requests, not for login/register attempts.
        const isAuthEndpoint = url.startsWith('/auth/');
        if (status === 401 && !isAuthEndpoint) {
            useAuthStore.getState().logout();
            window.location.href = '/login';
        }

        // Redirect to /account if 2FA is enforced but not set up
        const message = (axiosError.response as any)?.data?.message;
        if (status === 403 && message === 'Two-factor authentication must be enabled.') {
            if (window.location.pathname !== '/account') {
                window.location.href = '/account';
            }
        }

        return Promise.reject(error);
    },
);

export default client;
