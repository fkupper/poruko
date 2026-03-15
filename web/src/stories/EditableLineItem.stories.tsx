import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { EditableLineItem } from '../components/EditableLineItem';

const meta = {
  title: 'Poruko/Primitives/Editable Line Item',
  component: EditableLineItem,
  parameters: {
    layout: 'padded',
  },
  decorators: [(Story) => <div className="max-w-2xl"><Story /></div>],
} satisfies Meta<typeof EditableLineItem>;

export default meta;
type Story = StoryObj<typeof meta>;

function EditableLineItemDemo({
  deleteDisabled = false,
  errorMessage,
}: {
  deleteDisabled?: boolean;
  errorMessage?: string;
}) {
  const [description, setDescription] = useState('Salary');
  const [amount, setAmount] = useState('6000');

  return (
    <EditableLineItem
      amountInputProps={{
        value: amount,
        onChange: (event) => setAmount(event.currentTarget.value),
        'aria-label': 'Income amount',
      }}
      deleteDisabled={deleteDisabled}
      deleteLabel="Delete income line item"
      descriptionInputProps={{
        value: description,
        onChange: (event) => setDescription(event.currentTarget.value),
        placeholder: 'Description',
        'aria-label': 'Income description',
      }}
      errorMessage={errorMessage}
      onDelete={() => {
        setDescription('');
        setAmount('0');
      }}
    />
  );
}

export const Default: Story = {
  render: () => <EditableLineItemDemo />,
};

export const DeleteDisabled: Story = {
  render: () => <EditableLineItemDemo deleteDisabled />,
};

export const ErrorState: Story = {
  render: () => <EditableLineItemDemo errorMessage="Description is required." />,
};
