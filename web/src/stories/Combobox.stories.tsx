import type { Meta, StoryObj } from '@storybook/react';
import { useState } from 'react';
import { Combobox } from '../components/Combobox';

const meta: Meta<typeof Combobox> = {
  title: 'Poruko/Primitives/Combobox',
  component: Combobox,
};

export default meta;

type Story = StoryObj<typeof Combobox>;

const exampleOptions = [
  { value: 'America/New_York', label: 'America/New_York' },
  { value: 'Europe/Amsterdam', label: 'Europe/Amsterdam' },
  { value: 'Asia/Tokyo', label: 'Asia/Tokyo' },
];

const longOptions = [
  { value: 'Africa/Abidjan', label: 'Africa/Abidjan' },
  { value: 'Africa/Accra', label: 'Africa/Accra' },
  { value: 'Africa/Addis_Ababa', label: 'Africa/Addis_Ababa' },
  { value: 'Africa/Algiers', label: 'Africa/Algiers' },
  { value: 'Africa/Asmara', label: 'Africa/Asmara' },
  { value: 'Africa/Bamako', label: 'Africa/Bamako' },
  { value: 'Africa/Bangui', label: 'Africa/Bangui' },
  { value: 'Africa/Banjul', label: 'Africa/Banjul' },
  { value: 'Africa/Bissau', label: 'Africa/Bissau' },
  { value: 'Africa/Blantyre', label: 'Africa/Blantyre' },
  { value: 'Africa/Brazzaville', label: 'Africa/Brazzaville' },
  { value: 'Africa/Bujumbura', label: 'Africa/Bujumbura' },
  { value: 'Africa/Cairo', label: 'Africa/Cairo' },
  { value: 'Africa/Casablanca', label: 'Africa/Casablanca' },
];

export const Default: Story = {
  render: function Render() {
    const [value, setValue] = useState('');

    return (
      <div className="max-w-xs">
        <Combobox
          value={value}
          onChange={setValue}
          options={exampleOptions}
          placeholder="Select a timezone"
        />
      </div>
    );
  },
};

export const Disabled: Story = {
  render: function Render() {
    const [value, setValue] = useState('Europe/Amsterdam');

    return (
      <div className="max-w-xs">
        <Combobox
          value={value}
          onChange={setValue}
          options={exampleOptions}
          placeholder="Select a timezone"
          disabled
        />
      </div>
    );
  },
};

export const NoMatches: Story = {
  render: function Render() {
    const [value, setValue] = useState('Mars/Colony');

    return (
      <div className="max-w-xs">
        <Combobox
          value={value}
          onChange={setValue}
          options={exampleOptions}
          placeholder="Type to filter"
        />
      </div>
    );
  },
};

export const LongList: Story = {
  render: function Render() {
    const [value, setValue] = useState('');

    return (
      <div className="max-w-xs">
        <Combobox
          value={value}
          onChange={setValue}
          options={longOptions}
          placeholder="Open and scroll options"
        />
      </div>
    );
  },
};

