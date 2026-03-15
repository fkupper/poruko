import type { Meta, StoryObj } from '@storybook/react-vite';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useEffect } from 'react';
import { MyFinancesPage } from '../features/ledger/MyFinancesPage';
import type { FinancialProfile } from '../api/types';

type StoryState = 'loaded' | 'empty' | 'slowSave';

const baseProfile: FinancialProfile = {
  id: 10,
  ledger_id: 1,
  user_id: 1,
  valid_from: '2026-03-01',
  valid_to: null,
  incomes: [
    { description: 'Salary', amount: 600000 },
    { description: 'Side gig', amount: 50000 },
  ],
  deductions: [
    { description: 'Federal Tax', amount: 90000 },
    { description: 'State Tax', amount: 30000 },
    { description: '401k', amount: 36000 },
    { description: 'Health Insurance', amount: 50000 },
  ],
  computed: {
    total_income: 650000,
    total_deductions: 206000,
    shareable_income: 444000,
  },
};

function buildMockFetch(state: StoryState): typeof fetch {
  return async (input, init) => {
    const url = typeof input === 'string' ? input : input.url;
    const isProfilePath = url.includes('/api/ledgers/1/users/1/financial-profile/active');

    if (!isProfilePath) {
      return new Response('Not found', { status: 404 });
    }

    if ((init?.method ?? 'GET') === 'PUT') {
      if (state === 'slowSave') {
        await new Promise((resolve) => setTimeout(resolve, 1500));
      }

      return new Response(JSON.stringify({ data: baseProfile }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    }

    if (state === 'empty') {
      return new Response('API request failed with status 404', { status: 404 });
    }

    return new Response(JSON.stringify({ data: baseProfile }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    });
  };
}

function StoryShell({ state }: { state: StoryState }) {
  useEffect(() => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = buildMockFetch(state);

    return () => {
      globalThis.fetch = originalFetch;
    };
  }, [state]);

  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: Infinity,
        retry: false,
        refetchOnMount: false,
        refetchOnWindowFocus: false,
      },
    },
  });

  return (
    <QueryClientProvider client={queryClient}>
      <div className="min-h-screen bg-background">
        <MyFinancesPage ledgerId={1} userId={1} />
      </div>
    </QueryClientProvider>
  );
}

const meta = {
  title: 'Poruko/Pages/My Finances',
  component: MyFinancesPage,
  parameters: {
    layout: 'fullscreen',
  },
} satisfies Meta<typeof MyFinancesPage>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Loaded: Story = {
  render: () => <StoryShell state="loaded" />,
};

export const EmptyProfile: Story = {
  render: () => <StoryShell state="empty" />,
};

export const SavingState: Story = {
  render: () => <StoryShell state="slowSave" />,
};
