import type { ReactElement } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { SidebarProvider } from '@/components/ui/sidebar';
import { useLedgerStore } from '@/stores/ledgerStore';

import { SpaceSwitcher, type SpaceSwitcherItem } from './SpaceSwitcher';

const spaceA: SpaceSwitcherItem = {
    id: 1,
    name: 'Alpha Space',
    settlementMode: 'direct_p2p',
};

const spaceB: SpaceSwitcherItem = {
    id: 2,
    name: 'Beta Space',
    settlementMode: 'joint_clearinghouse',
};

function renderWithSidebar(ui: ReactElement) {
    return render(<SidebarProvider defaultOpen>{ui}</SidebarProvider>);
}

describe('SpaceSwitcher', () => {
    afterEach(() => {
        cleanup();
    });

    beforeEach(() => {
        useLedgerStore.setState({ activeLedgerId: null });
    });

    it('renders skeleton loading state', () => {
        renderWithSidebar(<SpaceSwitcher spaces={[]} isLoading />);

        const trigger = screen.getByRole('button');
        expect(trigger).toBeDisabled();
        expect(trigger.querySelectorAll('[data-slot="skeleton"]')).toHaveLength(3);
    });

    it('renders error state and calls onRetry when the button is clicked', async () => {
        const user = userEvent.setup();
        const onRetry = vi.fn();

        renderWithSidebar(<SpaceSwitcher spaces={[]} isError onRetry={onRetry} />);

        expect(screen.getByText('Spaces unavailable')).toBeInTheDocument();
        expect(screen.getByText('Click to retry')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /spaces unavailable/i }));

        expect(onRetry).toHaveBeenCalledTimes(1);
    });

    it('does not show retry hint when isError and onRetry is omitted', () => {
        renderWithSidebar(<SpaceSwitcher spaces={[]} isError />);

        expect(screen.getByText('Spaces unavailable')).toBeInTheDocument();
        expect(screen.queryByText('Click to retry')).not.toBeInTheDocument();
    });

    it('shows empty state when there are no spaces', () => {
        renderWithSidebar(<SpaceSwitcher spaces={[]} />);

        expect(screen.getByText('No spaces')).toBeInTheDocument();
        expect(screen.getByText('Add a ledger')).toBeInTheDocument();
    });

    it('shows the first space by default with settlement label', () => {
        renderWithSidebar(<SpaceSwitcher spaces={[spaceB, spaceA]} />);

        expect(screen.getByRole('button', { name: /beta space/i })).toBeInTheDocument();
        expect(screen.getByText('Joint Clearing')).toBeInTheDocument();
    });

    it('lets the user pick another space from the menu', async () => {
        const user = userEvent.setup();

        renderWithSidebar(<SpaceSwitcher spaces={[spaceA, spaceB]} />);

        await user.click(screen.getByRole('button', { name: /alpha space/i }));

        const menu = screen.getByRole('menu');
        await user.click(within(menu).getByRole('menuitem', { name: /beta space/i }));

        expect(screen.getByRole('button', { name: /beta space/i })).toBeInTheDocument();
        expect(screen.getByText('Joint Clearing')).toBeInTheDocument();
    });

    it('lists spaces label, shortcuts, and add space in the menu', async () => {
        const user = userEvent.setup();

        renderWithSidebar(<SpaceSwitcher spaces={[spaceA]} />);

        await user.click(screen.getByRole('button', { name: /alpha space/i }));

        const menu = screen.getByRole('menu');
        expect(within(menu).getByText('Spaces')).toBeInTheDocument();
        expect(within(menu).getByText('⌘1')).toBeInTheDocument();
        expect(within(menu).getByRole('menuitem', { name: /add space/i })).toBeInTheDocument();
    });

    it('resets selection to the first space when the active id is no longer in the list', () => {
        const { rerender } = renderWithSidebar(<SpaceSwitcher spaces={[spaceA, spaceB]} />);

        expect(screen.getByRole('button', { name: /alpha space/i })).toBeInTheDocument();

        rerender(
            <SidebarProvider defaultOpen>
                <SpaceSwitcher spaces={[spaceB]} />
            </SidebarProvider>,
        );

        expect(screen.getByRole('button', { name: /beta space/i })).toBeInTheDocument();
    });
});
