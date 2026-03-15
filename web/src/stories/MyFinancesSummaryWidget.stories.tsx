import type { Meta, StoryObj } from '@storybook/react-vite';
import { DollarSign, TrendingUp, XCircle } from 'lucide-react';
import { SummaryCard } from '../components/SummaryCard';

function MyFinancesSummaryWidget() {
  return (
    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <SummaryCard
        amount="$6,500.00"
        descriptor="2 income sources"
        icon={<TrendingUp size={14} />}
        label="Total Monthly Income"
        tone="inflow"
      />
      <SummaryCard
        amount="$2,060.00"
        descriptor="4 deductions"
        icon={<XCircle size={14} />}
        label="Total Deductions"
        tone="destructive"
      />
      <SummaryCard
        amount="$4,440.00"
        descriptor="Used for proportional splits"
        icon={<DollarSign size={14} />}
        label="Shareable Income"
        tone="info"
      />
    </section>
  );
}

const meta = {
  title: 'Poruko/Widgets/My Finances Summary',
  component: MyFinancesSummaryWidget,
  parameters: {
    layout: 'padded',
  },
} satisfies Meta<typeof MyFinancesSummaryWidget>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};
