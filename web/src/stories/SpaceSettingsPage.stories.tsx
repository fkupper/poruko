import type { Meta, StoryObj } from '@storybook/react-vite';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { SpaceSettingsPage } from '../features/ledger/SpaceSettingsPage';

const queryClient = new QueryClient();

const meta = {
  title: 'Poruko/Pages/Space Settings',
  component: SpaceSettingsPage,
  parameters: {
    layout: 'padded',
    docs: {
      description: {
        component: 'Cutoff configuration controls execution timing, while settlements always cover the previous calendar month.',
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
} satisfies Meta<typeof SpaceSettingsPage>;

export default meta;
type Story = StoryObj<typeof meta>;

export const DefaultSettings: Story = {
  args: {
    ledgerId: 1,
  },
};

export const MobileSettings: Story = {
  args: {
    ledgerId: 1,
  },
  parameters: {
    viewport: {
      defaultViewport: 'mobile1',
    },
  },
};

