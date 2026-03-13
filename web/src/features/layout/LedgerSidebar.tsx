import { NavLink } from 'react-router-dom';

export interface LedgerOption {
  id: number;
  name: string;
  membersLabel: string;
}

interface LedgerSidebarProps {
  activeLedgerId: number | null;
  ledgers: LedgerOption[];
  userEmail: string;
  apiStatus: string;
  onLedgerChange: (ledgerId: number) => void;
  onLogout: () => void;
  isLoggingOut: boolean;
}

interface LedgerPickerProps {
  activeLedgerId: number | null;
  ledgers: LedgerOption[];
  onLedgerChange: (ledgerId: number) => void;
}

const NAV_ITEMS = [
  { label: 'Dashboard', to: (ledgerId: number) => `/ledgers/${ledgerId}/dashboard` },
  { label: 'Accounts', to: (ledgerId: number) => `/ledgers/${ledgerId}/accounts` },
  { label: 'My Finances', to: (ledgerId: number) => `/ledgers/${ledgerId}/my-finances` },
] as const;

function LedgerPicker({ activeLedgerId, ledgers, onLedgerChange }: LedgerPickerProps) {
  const selectedValue = activeLedgerId === null ? '' : String(activeLedgerId);
  const hasLedgers = ledgers.length > 0;

  return (
    <div className="sidebar-ledger-card">
      <label className="sidebar-ledger-label" htmlFor="ledger-picker">
        Space
      </label>
      <select
        className="sidebar-ledger-picker"
        disabled={!hasLedgers}
        id="ledger-picker"
        onChange={(event) => onLedgerChange(Number(event.target.value))}
        value={hasLedgers ? selectedValue : ''}
      >
        {hasLedgers ? (
          ledgers.map((ledger) => (
            <option key={ledger.id} value={ledger.id}>
              {ledger.name} - {ledger.membersLabel}
            </option>
          ))
        ) : (
          <option value="">No spaces available</option>
        )}
      </select>
    </div>
  );
}

export function LedgerSidebar({
  activeLedgerId,
  ledgers,
  userEmail,
  apiStatus,
  onLedgerChange,
  onLogout,
  isLoggingOut,
}: LedgerSidebarProps) {
  const canRenderNavigation = activeLedgerId !== null;

  return (
    <aside className="sidebar-shell hidden md:flex">
      <div className="sidebar-brand">
        <div className="sidebar-brand-logo" aria-hidden="true">
          PO
        </div>
        <div>
          <p className="text-sm font-semibold text-foreground">Poruko</p>
          <p className="text-xs text-muted-foreground">Shared household space</p>
        </div>
      </div>

      <LedgerPicker activeLedgerId={activeLedgerId} ledgers={ledgers} onLedgerChange={onLedgerChange} />

      <nav aria-label="Main navigation" className="sidebar-nav">
        {canRenderNavigation ? (
          NAV_ITEMS.map((item) => (
            <NavLink
              className={({ isActive }) => (isActive ? 'sidebar-nav-link sidebar-nav-link-active' : 'sidebar-nav-link')}
              key={item.label}
              to={item.to(activeLedgerId)}
            >
              {item.label}
            </NavLink>
          ))
        ) : (
          <p className="px-2 text-sm text-muted-foreground">Select a space to continue.</p>
        )}
      </nav>

      <div className="sidebar-footer">
        <div className="rounded-control border border-border bg-background px-3 py-2">
          <p className="truncate text-sm text-foreground">{userEmail}</p>
          <p className="text-xs text-muted-foreground">{apiStatus}</p>
        </div>
        <button className="btn-outline w-full justify-center py-2" disabled={isLoggingOut} onClick={onLogout} type="button">
          {isLoggingOut ? 'Signing out...' : 'Sign out'}
        </button>
      </div>
    </aside>
  );
}

interface MobileLedgerBarProps {
  activeLedgerId: number | null;
  ledgers: LedgerOption[];
  onLedgerChange: (ledgerId: number) => void;
}

export function MobileLedgerBar({ activeLedgerId, ledgers, onLedgerChange }: MobileLedgerBarProps) {
  return (
    <div className="border-b border-border bg-surface px-4 py-3 md:hidden">
      <LedgerPicker activeLedgerId={activeLedgerId} ledgers={ledgers} onLedgerChange={onLedgerChange} />
    </div>
  );
}
