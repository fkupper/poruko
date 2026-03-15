import type { Meta, StoryObj } from '@storybook/react-vite';
import { ArrowLeftRight, Check, CreditCard, Plus, Save, Settings, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';

const meta = {
  title: 'Poruko/Primitives/Buttons',
  parameters: {
    layout: 'padded',
  },
} satisfies Meta;

export default meta;
type Story = StoryObj<typeof meta>;

function IconLabel({ icon, label }: { icon: ReactNode; label: string }) {
  return (
    <span className="inline-flex items-center gap-2">
      <span className="inline-flex h-4 w-4 items-center justify-center">{icon}</span>
      <span>{label}</span>
    </span>
  );
}

export const AppButtonCatalog: Story = {
  render: () => (
    <div className="grid gap-4">
      <section className="panel">
        <div className="section-header">
          <h2 className="section-title">Core button classes</h2>
          <p className="section-subtitle">Primary, success, outline, and destructive patterns used across forms and actions.</p>
        </div>

        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <div className="grid gap-2">
            <button type="button" className="btn-primary">
              Save changes
            </button>
            <button type="button" className="btn-primary" disabled>
              Saving...
            </button>
            <button type="button" className="btn-primary">
              <IconLabel icon={<Save size={14} />} label="Save with icon" />
            </button>
          </div>

          <div className="grid gap-2">
            <button type="button" className="btn-success">
              New expense
            </button>
            <button type="button" className="btn-success text-sm">
              <IconLabel icon={<Plus size={14} />} label="Add account" />
            </button>
            <button type="button" className="btn-success" disabled>
              Creating...
            </button>
          </div>

          <div className="grid gap-2">
            <button type="button" className="btn-outline">
              Cancel
            </button>
            <button type="button" className="btn-outline w-full justify-center py-2">
              Sign out
            </button>
            <button type="button" className="btn-outline">
              <IconLabel icon={<Settings size={14} />} label="Configure" />
            </button>
          </div>

          <div className="grid gap-2">
            <button type="button" className="btn-destructive-outline">
              Remove
            </button>
            <button type="button" className="btn-destructive-outline">
              <IconLabel icon={<Trash2 size={14} />} label="Delete item" />
            </button>
            <button type="button" className="btn-destructive-outline" disabled>
              Removing...
            </button>
          </div>
        </div>
      </section>

      <section className="panel">
        <div className="section-header">
          <h2 className="section-title">Feature-specific buttons</h2>
          <p className="section-subtitle">Inline action styles used in dashboard, sidebars, and settings controls.</p>
        </div>

        <div className="mt-4 grid gap-4">
          <div className="grid gap-2">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Dashboard link-style action</p>
            <button
              type="button"
              className="text-xs font-medium text-info underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background rounded-full px-2 py-1"
            >
              <IconLabel icon={<ArrowLeftRight size={14} />} label="History" />
            </button>
          </div>

          <div className="grid gap-2">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Sidebar space picker trigger</p>
            <button type="button" className="sidebar-ledger-trigger w-full">
              <div className="flex min-w-0 items-center gap-3 text-left">
                <span aria-hidden="true" className="flex h-8 w-8 items-center justify-center rounded-control bg-surfaceStrong text-info">
                  <Check size={16} />
                </span>
                <div className="flex min-w-0 flex-col">
                  <span className="sidebar-ledger-label">Space</span>
                  <span className="truncate text-sm font-medium">123 Main Street</span>
                  <span className="truncate text-xs text-muted-foreground">2 members</span>
                </div>
              </div>
              <span className="ml-2 text-muted-foreground" aria-hidden="true">
                ▾
              </span>
            </button>
          </div>

          <div className="grid gap-2">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Space settings toggle buttons</p>
            <div className="flex items-center gap-3">
              <button type="button" aria-pressed="true" className="relative inline-flex h-5 w-9 items-center rounded-full bg-info transition-colors">
                <span className="inline-block h-4 w-4 translate-x-4 rounded-full bg-background shadow transition-transform" />
              </button>
              <button type="button" aria-pressed="false" className="relative inline-flex h-5 w-9 items-center rounded-full bg-surfaceStrong transition-colors">
                <span className="inline-block h-4 w-4 translate-x-1 rounded-full bg-foreground/60 shadow transition-transform" />
              </button>
              <button type="button" className="btn-primary text-sm">
                <IconLabel icon={<CreditCard size={14} />} label="Sample CTA" />
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>
  ),
};

