import { request } from './client';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
}

export interface RegisterInput {
  name: string;
  email: string;
  password: string;
}

export interface LoginInput {
  email: string;
  password: string;
}

export interface AuthResponse {
  user: AuthUser;
  token: string;
}

export interface MeResponse {
  user: AuthUser;
}

export interface LogoutResponse {
  message: string;
}

export function register(payload: RegisterInput): Promise<AuthResponse> {
  return request<AuthResponse>('/api/auth/register', {
    method: 'POST',
    body: payload,
  });
}

export function login(payload: LoginInput): Promise<AuthResponse> {
  return request<AuthResponse>('/api/auth/login', {
    method: 'POST',
    body: payload,
  });
}

export function fetchMe(token?: string | null): Promise<MeResponse> {
  return request<MeResponse>('/api/auth/me', {
    token,
  });
}

export function logout(token?: string | null): Promise<LogoutResponse> {
  return request<LogoutResponse>('/api/auth/logout', {
    method: 'POST',
    token,
  });
}
