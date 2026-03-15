import type { Meta, StoryObj } from '@storybook/react-vite';
import { MemoryRouter } from 'react-router-dom';
import { LedgerSidebar, MobileLedgerBar, type LedgerOption } from '../features/layout/LedgerSidebar.tsx';

const sampleSpaces: LedgerOption[] = [
  { id: 1, name: '123 Main Street', membersLabel: '2 members' },
  { id: 2, name: '456 Oak Avenue', membersLabel: '3 members' },
  { id: 8, name: 'Downtown Flat', membersLabel: '4 members' },
];

const meta = {
  title: 'Poruko/Navigation/Space Sidebar',
  component: LedgerSidebar,
  parameters: {
    layout: 'fullscreen',
  },
  decorators: [
    (Story) => (
      <MemoryRouter initialEntries={['/ledgers/1/accounts']}>
        <Story />
      </MemoryRouter>
    ),
  ],
} satisfies Meta<typeof LedgerSidebar>;

export default meta;
type Story = StoryObj<typeof meta>;

export const DesktopDefault: Story = {
  args: {
    activeLedgerId: 1,
    apiStatus: 'API: ok',
    isLoggingOut: false,
    ledgers: sampleSpaces,
    onLedgerChange: () => undefined,
    onLogout: () => undefined,
    userEmail: 'alex@example.com',
  },
  render: (args) => (
    <div className="min-h-screen bg-background md:pl-64">
      <LedgerSidebar {...args} />
      <main className="p-6">
        <div className="panel">
          <div className="section-header">
            <h2 className="section-title">Page content</h2>
            <p className="section-subtitle">
              Sidebar is fixed to the left, with route-aware navigation and space selection.
            </p>
          </div>
        </div>
      </main>
    </div>
  ),
};

export const EmptySpaceState: Story = {
  args: {
    activeLedgerId: null,
    apiStatus: 'API: checking...',
    isLoggingOut: false,
    ledgers: [],
    onLedgerChange: () => undefined,
    onLogout: () => undefined,
    userEmail: 'alex@example.com',
  },
  render: (args) => (
    <div className="min-h-screen bg-background md:pl-64">
      <LedgerSidebar {...args} />
      <main className="p-6">
        <p className="text-sm text-muted-foreground">Empty state for first-time space setup.</p>
      </main>
    </div>
  ),
};

export const MobilePicker: Story = {
  args: {
    activeLedgerId: 2,
    apiStatus: 'API: ok',
    isLoggingOut: false,
    ledgers: sampleSpaces,
    onLedgerChange: () => undefined,
    onLogout: () => undefined,
    userEmail: 'alex@example.com',
  },
  render: (args) => (
    <div className="min-h-screen bg-background">
      <MobileLedgerBar {...args} />
      <main className="p-4">
        <div className="panel">
          <div className="section-header">
            <h2 className="section-title">Mobile content area</h2>
            <p className="section-subtitle">
              The space picker remains available at small breakpoints.
            </p>
          </div>
        </div>
      </main>
    </div>
  ),
};
