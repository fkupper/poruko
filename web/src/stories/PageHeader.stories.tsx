import type { Meta, StoryObj } from '@storybook/react-vite';
import { PageHeader } from '../components/PageHeader';
import { SectionBlock } from '../components/SectionBlock';

const meta = {
  title: 'Poruko/Layouts/Page Header',
  component: PageHeader,
  parameters: {
    layout: 'padded',
  },
} satisfies Meta<typeof PageHeader>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    title: 'Space settings',
    subtitle: 'Control how this space settles up at the end of a cycle.',
  },
  render: (args) => (
    <main className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8">
      <PageHeader {...args} />
      <SectionBlock
        title="Settlement cycle"
        subtitle="Configure how and when this space settles up."
      >
        <div className="grid gap-2 text-sm text-muted-foreground">
          <p>Settlement timezone</p>
          <p>Monthly Settlement Day</p>
          <p>Cutoff time (HH:MM:SS)</p>
        </div>
      </SectionBlock>
    </main>
  ),
};

export const WithActions: Story = {
  args: {
    title: 'Manage accounts',
    subtitle: 'Create and organize payment accounts for this space.',
    actions: (
      <button type="button" className="btn-primary text-sm">
        Add account
      </button>
    ),
  },
};

