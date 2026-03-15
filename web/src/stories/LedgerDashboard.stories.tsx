import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { ComponentType } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { LedgerDashboardPage } from '../features/ledger/LedgerDashboardPage';

const queryClient = new QueryClient();

const meta = {
  title: 'Poruko/Pages/Ledger Dashboard',
  component: LedgerDashboardPage,
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component: 'Dashboard member balances summarize the previous calendar month settlement window.',
      },
    },
  },
  decorators: [
    (Story: ComponentType) => (
      <MemoryRouter initialEntries={['/ledgers/1/dashboard']}>
        <QueryClientProvider client={queryClient}>
          <div className="min-h-screen bg-background">
            <Story />
          </div>
        </QueryClientProvider>
      </MemoryRouter>
    ),
  ],
};

export default meta;

export const DefaultDashboard = {
  args: {
    ledgerId: 1,
  },
};

