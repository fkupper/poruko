import type { Meta, StoryObj } from '@storybook/react-vite';
import { CreditCard, LayoutGrid } from 'lucide-react';
import { MemoryRouter } from 'react-router-dom';
import { SidebarMenuItem } from '../components/SidebarMenuItem';

const meta = {
  title: 'Poruko/Navigation/Sidebar Menu Item',
  component: SidebarMenuItem,
  parameters: {
    layout: 'padded',
  },
  decorators: [
    (Story) => (
      <MemoryRouter initialEntries={['/ledgers/1/accounts']}>
        <div className="w-72 rounded-card border border-border bg-surface p-3">
          <Story />
        </div>
      </MemoryRouter>
    ),
  ],
} satisfies Meta<typeof SidebarMenuItem>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    to: '/ledgers/1/dashboard',
    label: 'Dashboard',
    icon: <LayoutGrid />,
  },
};

export const Active: Story = {
  args: {
    to: '/ledgers/1/accounts',
    label: 'Accounts',
    icon: <CreditCard />,
  },
};

export const WithoutIcon: Story = {
  args: {
    to: '/ledgers/1/settings',
    label: 'Space Settings',
  },
};

