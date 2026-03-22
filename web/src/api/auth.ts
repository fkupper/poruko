import client from './client';
import type { AuthResponse, User } from './types';

interface LoginPayload {
    email: string;
    password: string;
}

interface RegisterPayload {
    name: string;
    email: string;
    password: string;
}

export const login = async (payload: LoginPayload): Promise<AuthResponse> => {
    const { data } = await client.post<AuthResponse>('/auth/login', payload);
    return data;
};

export const register = async (payload: RegisterPayload): Promise<AuthResponse> => {
    const { data } = await client.post<AuthResponse>('/auth/register', payload);
    return data;
};

export const me = async (): Promise<{ user: User }> => {
    const { data } = await client.get<{ user: User }>('/auth/me');
    return data;
};

export const logout = async (): Promise<void> => {
    await client.post('/auth/logout');
};
