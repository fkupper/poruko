import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ACCESS_TOKEN_STORAGE_KEY, fetchHealth, registerOnUnauthorized } from './client';
import { useAuthStore } from '../stores/authStore';

describe('API client 401 handling', () => {
  const originalFetch = globalThis.fetch;

  beforeEach(() => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockImplementation(() => {
        return Promise.resolve(
          new Response(JSON.stringify({ message: 'Unauthenticated.' }), {
            status: 401,
            headers: { 'Content-Type': 'application/json' },
          }),
        );
      }),
    );
  });

  afterEach(() => {
    vi.stubGlobal('fetch', originalFetch);
    localStorage.removeItem(ACCESS_TOKEN_STORAGE_KEY);
    useAuthStore.getState().setUnauthenticated();
  });

  it('invokes onUnauthorized callback when request returns 401', async () => {
    const onUnauthorized = vi.fn();
    registerOnUnauthorized(onUnauthorized);

    await expect(fetchHealth()).rejects.toThrow();

    expect(onUnauthorized).toHaveBeenCalledTimes(1);
  });

  it('clears auth store when 401 is received and real callback is registered', async () => {
    registerOnUnauthorized(() => useAuthStore.getState().setUnauthenticated?.());

    useAuthStore.setState({
      user: { id: 1, name: 'Test', email: 'test@example.com' },
      token: 'fake-token',
      isBootstrapping: false,
    });
    localStorage.setItem(ACCESS_TOKEN_STORAGE_KEY, 'fake-token');

    await expect(fetchHealth()).rejects.toThrow();

    expect(useAuthStore.getState().user).toBeNull();
    expect(useAuthStore.getState().token).toBeNull();
    expect(localStorage.getItem(ACCESS_TOKEN_STORAGE_KEY)).toBeNull();
  });
});
