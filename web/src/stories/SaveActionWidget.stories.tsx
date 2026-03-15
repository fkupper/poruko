import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { AsyncSaveButton } from '../components/AsyncSaveButton';

function SaveActionWidgetDemo() {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);

  function handleSaveClick() {
    setIsSubmitting(true);
    setIsSuccess(false);

    window.setTimeout(() => {
      setIsSubmitting(false);
      setIsSuccess(true);
    }, 1000);
  }

  return (
    <section className="panel max-w-2xl">
      <div className="section-header">
        <h2 className="section-title">Save action footer</h2>
        <p className="section-subtitle">Widget-level save action with async visual feedback.</p>
      </div>
      <div className="mt-4 flex items-center justify-between gap-4 rounded-card border border-border bg-background p-3">
        <p className="text-sm text-muted-foreground">Pending edits in this section</p>
        <AsyncSaveButton
          className="text-sm"
          isSubmitting={isSubmitting}
          isSuccess={isSuccess}
          label="Save changes"
          onClick={handleSaveClick}
          type="button"
        />
      </div>
    </section>
  );
}

const meta = {
  title: 'Poruko/Widgets/Save Action',
  component: SaveActionWidgetDemo,
  parameters: {
    layout: 'padded',
  },
} satisfies Meta<typeof SaveActionWidgetDemo>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};
