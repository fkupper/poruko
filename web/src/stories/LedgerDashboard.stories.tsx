import type { Meta, StoryObj } from '@storybook/react-vite';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { LedgerDashboardPage } from '../features/ledger/LedgerDashboardPage.tsx';

const queryClient = new QueryClient();

const meta = {
  title: 'Poruko/Ledger Dashboard',
  component: LedgerDashboardPage,
  parameters: {
    layout: 'fullscreen',
  },
  decorators: [
    (Story) => (
      <MemoryRouter initialEntries={['/ledgers/1/dashboard']}>
        <QueryClientProvider client={queryClient}>
          <div className="min-h-screen bg-background">
            <Story />
          </div>
        </QueryClientProvider>
      </MemoryRouter>
    ),
  ],
} satisfies Meta<typeof LedgerDashboardPage>;

export default meta;
type Story = StoryObj<typeof meta>;

export const DefaultDashboard: Story = {
  args: {
    ledgerId: 1,
  },
};

