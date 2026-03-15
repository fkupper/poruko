import type { Meta, StoryObj } from '@storybook/react-vite';
import { useEffect, useState } from 'react';
import { AsyncSaveButton } from '../components/AsyncSaveButton';

const meta = {
  title: 'Poruko/Primitives/Async Save Button',
  component: AsyncSaveButton,
  parameters: {
    layout: 'padded',
  },
} satisfies Meta<typeof AsyncSaveButton>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Idle: Story = {
  args: {
    label: 'Save changes',
    isSubmitting: false,
    isSuccess: false,
    type: 'button',
  },
};

export const Submitting: Story = {
  args: {
    label: 'Save changes',
    isSubmitting: true,
    isSuccess: false,
    type: 'button',
  },
};

export const Disabled: Story = {
  args: {
    label: 'Save changes',
    isSubmitting: false,
    isSuccess: false,
    disabled: true,
    type: 'button',
  },
};

export const ErrorFlash: Story = {
  args: {
    label: 'Save changes',
    isSubmitting: false,
    isSuccess: false,
    isError: true,
    type: 'button',
  },
};

function SuccessFlashDemo() {
  const [isSubmitting, setIsSubmitting] = useState(true);
  const [isSuccess, setIsSuccess] = useState(false);

  useEffect(() => {
    const settleTimer = window.setTimeout(() => {
      setIsSubmitting(false);
      setIsSuccess(true);
    }, 900);

    const resetTimer = window.setTimeout(() => {
      setIsSuccess(false);
    }, 2000);

    return () => {
      window.clearTimeout(settleTimer);
      window.clearTimeout(resetTimer);
    };
  }, []);

  return (
    <AsyncSaveButton
      isSubmitting={isSubmitting}
      isSuccess={isSuccess}
      label="Save changes"
      type="button"
    />
  );
}

export const SuccessFlash: Story = {
  render: () => <SuccessFlashDemo />,
};
