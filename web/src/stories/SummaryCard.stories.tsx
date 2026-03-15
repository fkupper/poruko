import type { Meta, StoryObj } from '@storybook/react-vite';
import { DollarSign, TrendingUp, XCircle } from 'lucide-react';
import { SummaryCard } from '../components/SummaryCard';

const meta = {
  title: 'Poruko/Primitives/Summary Card',
  component: SummaryCard,
  parameters: {
    layout: 'padded',
  },
  decorators: [(Story) => <div className="max-w-sm"><Story /></div>],
} satisfies Meta<typeof SummaryCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Inflow: Story = {
  args: {
    label: 'Total Monthly Income',
    amount: '$6,500.00',
    descriptor: '2 income sources',
    tone: 'inflow',
    icon: <TrendingUp size={14} />,
  },
};

export const Destructive: Story = {
  args: {
    label: 'Total Deductions',
    amount: '$2,060.00',
    descriptor: '4 deductions',
    tone: 'destructive',
    icon: <XCircle size={14} />,
  },
};

export const Info: Story = {
  args: {
    label: 'Shareable Income',
    amount: '$4,440.00',
    descriptor: 'Used for proportional splits',
    tone: 'info',
    icon: <DollarSign size={14} />,
  },
};

export const WithoutData: Story = {
  args: {
    label: 'Shareable Income',
    amount: '$0.00',
    descriptor: 'Add income and deductions to calculate',
    tone: 'info',
  },
};
