import type { Meta, StoryObj } from '@storybook/react-vite';
import { PageScaffold } from '../components/PageScaffold';
import { SectionBlock } from '../components/SectionBlock';

const meta = {
  title: 'Poruko/Layouts/Page Scaffold',
  component: PageScaffold,
  parameters: {
    layout: 'fullscreen',
  },
} satisfies Meta<typeof PageScaffold>;

export default meta;
type Story = StoryObj<typeof meta>;

export const SpaceSettingsLayout: Story = {
  args: {
    title: 'Space settings',
    subtitle: 'Control how this space settles up at the end of a cycle.',
  },
  render: (args) => (
    <div className="min-h-screen bg-background text-foreground md:pl-64">
      <PageScaffold {...args}>
        <SectionBlock title="Settlement cycle" subtitle="Configure how and when this space settles up.">
          <div className="grid gap-3 text-sm">
            <label className="grid gap-1">
              <span>Settlement timezone</span>
              <input className="field-input" defaultValue="Europe/Amsterdam" />
            </label>
            <label className="grid gap-1">
              <span>Monthly Settlement Day</span>
              <input className="field-input" type="number" defaultValue={5} />
            </label>
            <label className="grid gap-1">
              <span>Cutoff time (HH:MM:SS)</span>
              <input className="field-input" defaultValue="23:59:00" />
            </label>
          </div>
        </SectionBlock>

        <SectionBlock title="Notifications" subtitle="Choose what updates you want to receive.">
          <div className="grid gap-2 text-sm text-muted-foreground">
            <p>New Expense Alerts</p>
            <p>Settlement Reminders</p>
            <p>Weekly Summary</p>
          </div>
        </SectionBlock>
      </PageScaffold>
    </div>
  ),
};

export const WithHeaderAction: Story = {
  args: {
    title: 'Manage accounts',
    subtitle: 'Create and organize payment accounts for this space.',
    headerActions: (
      <button type="button" className="btn-primary text-sm">
        Add account
      </button>
    ),
  },
  render: (args) => (
    <div className="min-h-screen bg-background text-foreground md:pl-64">
      <PageScaffold {...args}>
        <SectionBlock title="Accounts" subtitle="Primary and external accounts linked to this space.">
          <ul className="grid gap-2 text-sm">
            <li className="rounded-card border border-border px-3 py-2">House Pool (pool)</li>
            <li className="rounded-card border border-border px-3 py-2">My Wallet (personal)</li>
          </ul>
        </SectionBlock>
      </PageScaffold>
    </div>
  ),
};

export const MobileDense: Story = {
  args: {
    title: 'Space settings',
    subtitle: 'Control how this space settles up at the end of a cycle.',
  },
  parameters: {
    viewport: {
      defaultViewport: 'mobile1',
    },
  },
  render: (args) => (
    <div className="min-h-screen bg-background text-foreground">
      <PageScaffold {...args} className="gap-4 px-3 py-6">
        <SectionBlock title="Settlement cycle" subtitle="Configure how and when this space settles up.">
          <div className="grid gap-2 text-sm text-muted-foreground">
            <p>Settlement timezone</p>
            <p>Monthly Settlement Day</p>
            <p>Cutoff time (HH:MM:SS)</p>
          </div>
        </SectionBlock>
      </PageScaffold>
    </div>
  ),
};

