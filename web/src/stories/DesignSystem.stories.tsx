import type { Meta, StoryObj } from '@storybook/react';
import { useState } from 'react';
import { AsyncSaveButton } from '../components/AsyncSaveButton';
import { Combobox } from '../components/Combobox';

const meta = {
  title: 'Poruko/Foundations/Design System',
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
        <div className="section-header">
          <h3 className="section-title">Surface + Text</h3>
          <p className="section-subtitle">Panels, typography, and borders are token-backed.</p>
        </div>
        <div className="mt-3 grid gap-2 text-sm">
          <p className="text-foreground">Foreground text</p>
          <p className="text-muted-foreground">Muted foreground text</p>
          <p className="text-info">Info state text</p>
          <p className="text-inflow">Inflow state text</p>
          <p className="text-destructive">Destructive state text</p>
        </div>
      </div>

      <div className="panel">
        <div className="section-header">
          <h3 className="section-title">Buttons</h3>
        </div>
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
  render: function Render() {
    const [timezone, setTimezone] = useState('');

    return (
      <div className="panel max-w-2xl">
        <div className="section-header">
          <h3 className="section-title">Form Inputs + Numeric Alignment</h3>
          <p className="section-subtitle">
            Demonstrates tokenized fields, searchable comboboxes, and tabular numeric rendering.
          </p>
        </div>

        <div className="mt-4 grid gap-3 sm:grid-cols-[1fr,180px,auto]">
          <input className="field-input" defaultValue="Household groceries" />
          <input className="field-input amount-numeric" defaultValue="152.45" />
          <button className="btn-primary" type="button">
            Save
          </button>
        </div>

        <div className="mt-3 max-w-xs">
          <Combobox
            value={timezone}
            onChange={setTimezone}
            options={[
              { value: 'UTC', label: 'UTC' },
              { value: 'Europe/Amsterdam', label: 'Europe/Amsterdam' },
              { value: 'America/New_York', label: 'America/New_York' },
              { value: 'Asia/Tokyo', label: 'Asia/Tokyo' },
            ]}
            placeholder="Select a timezone"
          />
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
    );
  },
};

export const AsyncSaveStates: Story = {
  render: function Render() {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isSuccess, setIsSuccess] = useState(false);
    const [isError, setIsError] = useState(false);

    function runAsyncDemo() {
      setIsSubmitting(true);
      setIsSuccess(false);
      setIsError(false);

      window.setTimeout(() => {
        setIsSubmitting(false);
        setIsSuccess(true);
      }, 900);
    }

    function runAsyncErrorDemo() {
      setIsSubmitting(true);
      setIsSuccess(false);
      setIsError(false);

      window.setTimeout(() => {
        setIsSubmitting(false);
        setIsError(true);
      }, 900);
    }

    return (
      <div className="panel max-w-2xl">
        <div className="section-header">
          <h3 className="section-title">Async Save Motion Pattern</h3>
          <p className="section-subtitle">
            Primary save actions use icon-led feedback for submitting and success states.
          </p>
        </div>
        <div className="mt-4 flex flex-wrap items-center gap-3">
          <AsyncSaveButton
            isSubmitting={false}
            isSuccess={false}
            label="Idle"
            type="button"
          />
          <AsyncSaveButton
            isSubmitting
            isSuccess={false}
            label="Submitting"
            type="button"
          />
          <AsyncSaveButton
            isSubmitting={isSubmitting}
            isSuccess={isSuccess}
            label="Simulate success"
            onClick={runAsyncDemo}
            type="button"
          />
          <AsyncSaveButton
            isError={isError}
            isSubmitting={isSubmitting}
            isSuccess={false}
            label="Simulate error"
            onClick={runAsyncErrorDemo}
            type="button"
          />
        </div>
      </div>
    );
  },
};
