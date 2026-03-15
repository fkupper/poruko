import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import { SpaceSettingsPage } from './SpaceSettingsPage';

const mutateAsync = vi.fn().mockResolvedValue(undefined);

vi.mock('../../api/settlements', () => {
  return {
    useUpdateCycleConfigMutation: () => ({
      isPending: false,
      isSuccess: false,
      isError: false,
      error: undefined,
      mutateAsync,
    }),
  };
});

vi.mock('../../api/ledgers', () => {
  return {
    useLedgersQuery: () => ({ data: undefined }),
  };
});

function renderWithClient(ui: React.ReactElement) {
  const client = new QueryClient();

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>);
}

describe('SpaceSettingsPage', () => {
  it('submits cycle config', async () => {
    renderWithClient(<SpaceSettingsPage ledgerId={1} />);

    await act(async () => {
      fireEvent.change(screen.getByRole('combobox', { name: /Settlement timezone/ }), {
        target: { value: 'Europe/Amsterdam' },
      });
      fireEvent.change(screen.getByLabelText(/Settlement execution day \(cutoff day\)/i), { target: { value: '5' } });
      fireEvent.change(screen.getByLabelText(/Cutoff time \(HH:MM:SS\)/), { target: { value: '23:59:00' } });
      fireEvent.click(screen.getByText('Save settings'));
    });

    expect(mutateAsync).toHaveBeenCalledTimes(1);

    // Notifications section (mock) is rendered
    expect(screen.getByText('Notifications')).toBeInTheDocument();
  });
});

