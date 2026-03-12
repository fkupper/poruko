import type { Meta, StoryObj } from '@storybook/react-vite';

const meta = {
  title: 'Poruko/Design System',
  parameters: {
    layout: 'padded',
  },
} satisfies Meta;

export default meta;
type Story = StoryObj<typeof meta>;

export const SemanticColors: Story = {
  render: () => (
    <div className="grid gap-4 md:grid-cols-2">
      <div className="panel">
        <h3 className="text-lg font-semibold">Surface + Text</h3>
        <p className="mt-1 text-sm text-muted-foreground">
          Panels, typography, and borders are token-backed.
        </p>
        <div className="mt-3 grid gap-2 text-sm">
          <p className="text-foreground">Foreground text</p>
          <p className="text-muted-foreground">Muted foreground text</p>
          <p className="text-info">Info state text</p>
          <p className="text-inflow">Inflow state text</p>
          <p className="text-destructive">Destructive state text</p>
        </div>
      </div>

      <div className="panel">
        <h3 className="text-lg font-semibold">Buttons</h3>
        <div className="mt-3 flex flex-wrap gap-2">
          <button className="btn-primary" type="button">
            Primary
          </button>
          <button className="btn-success" type="button">
            Success
          </button>
          <button className="btn-outline" type="button">
            Outline
          </button>
          <button className="btn-destructive-outline" type="button">
            Remove
          </button>
        </div>
      </div>
    </div>
  ),
};

export const FormAndNumeric: Story = {
  render: () => (
    <div className="panel max-w-2xl">
      <h3 className="text-lg font-semibold">Form Inputs + Numeric Alignment</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        Demonstrates tokenized fields and tabular numeric rendering.
      </p>

      <div className="mt-4 grid gap-3 sm:grid-cols-[1fr,180px,auto]">
        <input className="field-input" defaultValue="Household groceries" />
        <input className="field-input amount-numeric" defaultValue="152.45" />
        <button className="btn-primary" type="button">
          Save
        </button>
      </div>

      <div className="mt-4 overflow-x-auto rounded-card border border-border">
        <table className="min-w-full text-sm">
          <thead className="bg-surface text-muted-foreground">
            <tr>
              <th className="px-3 py-2 text-left">Category</th>
              <th className="px-3 py-2 text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr className="border-t border-border">
              <td className="px-3 py-2 text-foreground">Rent</td>
              <td className="amount-numeric px-3 py-2 text-foreground">1200.00</td>
            </tr>
            <tr className="border-t border-border">
              <td className="px-3 py-2 text-foreground">Utilities</td>
              <td className="amount-numeric px-3 py-2 text-foreground">198.71</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  ),
};
