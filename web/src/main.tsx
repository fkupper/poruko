import { StrictMode, lazy, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import './index.css';
import App from './App.tsx';
import { queryClient } from './lib/queryClient.ts';

const Devtools = import.meta.env.DEV
  ? lazy(async () => {
      const mod = await import('@tanstack/react-query-devtools');
      return { default: mod.ReactQueryDevtools };
    })
  : null;

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <App />
        {Devtools !== null ? (
          <Suspense fallback={null}>
            <Devtools initialIsOpen={false} />
          </Suspense>
        ) : null}
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
);
