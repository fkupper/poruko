import type { Meta, StoryObj } from '@storybook/react-vite';
import { SectionBlock } from '../components/SectionBlock';

const meta = {
  title: 'Poruko/Primitives/Section Block',
  component: SectionBlock,
  parameters: {
    layout: 'padded',
  },
} satisfies Meta<typeof SectionBlock>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    title: 'Settlement cycle',
    subtitle: 'Configure how and when this space settles up.',
    children: (
      <div className="grid gap-2 text-sm text-muted-foreground">
        <p>Settlement timezone</p>
        <p>Monthly Settlement Day</p>
        <p>Cutoff time (HH:MM:SS)</p>
      </div>
    ),
  },
};

export const WithActions: Story = {
  args: {
    title: 'Recent activity',
    subtitle: 'Latest transactions and transfers for this cycle.',
    actions: (
      <button type="button" className="btn-primary text-sm">
        View all
      </button>
    ),
    children: (
      <ul className="divide-y divide-border text-sm">
        <li className="py-2">Groceries - 52.40</li>
        <li className="py-2">Utilities - 89.10</li>
      </ul>
    ),
  },
};

