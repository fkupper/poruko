import { useQuery } from '@tanstack/react-query';
import { fetchHealth, type HealthResponse } from './client';

export function useHealthQuery() {
  return useQuery<HealthResponse>({
    queryKey: ['health'],
    queryFn: fetchHealth,
  });
}

