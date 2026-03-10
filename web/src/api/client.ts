const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000';

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(init?.headers ?? {}),
    },
    ...init,
  });

  if (!response.ok) {
    // eslint-disable-next-line no-console
    console.error('API error', response.status, await response.text());
    throw new Error(`API request failed with status ${response.status}`);
  }

  return (await response.json()) as T;
}

export interface HealthResponse {
  status: 'ok';
}

export function fetchHealth(): Promise<HealthResponse> {
  return request<HealthResponse>('/api/health');
}

