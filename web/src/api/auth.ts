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

export interface LoginResponse {
    user: User;
    token: string;
    two_factor: boolean;
}

export const login = async (payload: LoginPayload): Promise<LoginResponse> => {
    const { data } = await client.post<LoginResponse>('/auth/login', payload);
    return data;
};

export const challengeTwoFactor = async (payload: { code?: string; recovery_code?: string }): Promise<AuthResponse> => {
    const { data } = await client.post<AuthResponse>('/auth/two-factor-challenge', payload);
    return data;
};

export const register = async (payload: RegisterPayload): Promise<AuthResponse> => {
    const { data } = await client.post<AuthResponse>('/auth/register', payload);
    return data;
};

export interface AcceptInvitationPayload extends RegisterPayload {
    token: string;
}

export const acceptInvitation = async (payload: AcceptInvitationPayload): Promise<AuthResponse> => {
    const { data } = await client.post<AuthResponse>('/invitations/accept', payload);
    return data;
};

export const me = async (): Promise<{ user: User }> => {
    const { data } = await client.get<{ user: User }>('/auth/me');
    return data;
};

export const logout = async (): Promise<void> => {
    await client.post('/auth/logout');
};
