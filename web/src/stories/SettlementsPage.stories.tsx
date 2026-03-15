import type { Meta, StoryObj } from '@storybook/react-vite';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { SettlementsPage } from '../features/ledger/SettlementsPage';

const queryClient = new QueryClient();

const meta = {
  title: 'Poruko/Pages/Settlements',
  component: SettlementsPage,
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component: 'Shows previous-calendar-month preview and settlement execution history.',
      },
    },
  },
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <div className="min-h-screen bg-background">
          <Story />
        </div>
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof SettlementsPage>;

export default meta;
type Story = StoryObj<typeof meta>;

export const DefaultSettlements: Story = {
  args: {
    ledgerId: 1,
  },
};

export const MobileSettlements: Story = {
  args: {
    ledgerId: 1,
  },
  parameters: {
    viewport: {
      defaultViewport: 'mobile1',
    },
  },
};

