import { useEffect, useId, useRef, useState } from 'react';
import { ArrowLeftRight, CreditCard, LayoutGrid, PiggyBank, Settings } from 'lucide-react';
import { SidebarMenuItem } from '../../components/SidebarMenuItem';

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
  { label: 'Dashboard', icon: <LayoutGrid />, to: (ledgerId: number) => `/ledgers/${ledgerId}/dashboard` },
  { label: 'Accounts', icon: <CreditCard />, to: (ledgerId: number) => `/ledgers/${ledgerId}/accounts` },
  { label: 'My Finances', icon: <PiggyBank />, to: (ledgerId: number) => `/ledgers/${ledgerId}/my-finances` },
  { label: 'Settlements', icon: <ArrowLeftRight />, to: (ledgerId: number) => `/ledgers/${ledgerId}/settlements` },
  { label: 'Space Settings', icon: <Settings />, to: (ledgerId: number) => `/ledgers/${ledgerId}/settings` },
] as const;

function LedgerPicker({ activeLedgerId, ledgers, onLedgerChange }: LedgerPickerProps) {
  const generatedId = useId();
  const triggerId = `ledger-picker-${generatedId}`;
  const listboxId = `${triggerId}-listbox`;
  const hasLedgers = ledgers.length > 0;
  const [isOpen, setIsOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const containerRef = useRef<HTMLDivElement | null>(null);
  const activeLedger =
    hasLedgers && activeLedgerId !== null
      ? ledgers.find((ledger) => ledger.id === activeLedgerId) ?? ledgers[0]
      : hasLedgers
        ? ledgers[0]
        : null;

  function handleSelect(ledgerId: number): void {
    onLedgerChange(ledgerId);
    setIsOpen(false);
    setActiveIndex(-1);
  }

  function openWithActiveLedger(): void {
    if (!hasLedgers) {
      return;
    }
    setIsOpen(true);
    const selectedIndex = ledgers.findIndex((ledger) => ledger.id === activeLedger?.id);
    setActiveIndex(selectedIndex >= 0 ? selectedIndex : 0);
  }

  useEffect(() => {
    function handleOutsideClick(event: MouseEvent): void {
      if (!containerRef.current?.contains(event.target as Node)) {
        setIsOpen(false);
        setActiveIndex(-1);
      }
    }

    if (isOpen) {
      window.addEventListener('mousedown', handleOutsideClick);
    }

    return () => window.removeEventListener('mousedown', handleOutsideClick);
  }, [isOpen]);

  return (
    <div ref={containerRef} className="sidebar-ledger-card">
      <button
        id={triggerId}
        type="button"
        className="sidebar-ledger-trigger"
        disabled={!hasLedgers}
        onClick={() => {
          if (!hasLedgers) {
            return;
          }
          if (isOpen) {
            setIsOpen(false);
            setActiveIndex(-1);
            return;
          }
          openWithActiveLedger();
        }}
        onKeyDown={(event) => {
          if (!hasLedgers) {
            return;
          }
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (!isOpen) {
              openWithActiveLedger();
              return;
            }
            setActiveIndex((current) => {
              if (current < 0) {
                return 0;
              }
              return (current + 1) % ledgers.length;
            });
          } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (!isOpen) {
              openWithActiveLedger();
              return;
            }
            setActiveIndex((current) => {
              if (current < 0) {
                return ledgers.length - 1;
              }
              return (current - 1 + ledgers.length) % ledgers.length;
            });
          } else if (event.key === 'Enter' && isOpen && activeIndex >= 0) {
            event.preventDefault();
            handleSelect(ledgers[activeIndex].id);
          } else if (event.key === 'Escape') {
            setIsOpen(false);
            setActiveIndex(-1);
          }
        }}
        aria-haspopup="listbox"
        aria-expanded={isOpen}
        aria-controls={listboxId}
      >
        <div className="flex min-w-0 items-center gap-3 text-left">
          <span
            aria-hidden="true"
            className="flex h-8 w-8 items-center justify-center rounded-control bg-surfaceStrong text-info"
          >
            <LayoutGrid className="h-4 w-4" />
          </span>
          <div className="flex min-w-0 flex-col">
            <span className="sidebar-ledger-label">Space</span>
            <span className="truncate text-sm font-medium">
              {activeLedger ? activeLedger.name : 'No spaces available'}
            </span>
            {activeLedger && (
              <span className="truncate text-xs text-muted-foreground">{activeLedger.membersLabel}</span>
            )}
          </div>
        </div>
        <span className="ml-2 text-muted-foreground" aria-hidden="true">
          ▾
        </span>
      </button>
      {isOpen && hasLedgers && (
        <div id={listboxId} className="sidebar-ledger-menu" role="listbox" aria-label="Select space">
          {ledgers.map((ledger, index) => {
            const isActive = activeLedger?.id === ledger.id;

            return (
              <button
                key={ledger.id}
                type="button"
                role="option"
                aria-selected={isActive}
                className={
                  isActive || index === activeIndex
                    ? 'sidebar-ledger-option sidebar-ledger-option-active'
                    : 'sidebar-ledger-option'
                }
                onMouseEnter={() => setActiveIndex(index)}
                onClick={() => handleSelect(ledger.id)}
              >
                <div className="truncate text-sm font-medium">{ledger.name}</div>
                <div className="truncate text-xs text-muted-foreground">{ledger.membersLabel}</div>
              </button>
            );
          })}
        </div>
      )}
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
            <SidebarMenuItem key={item.label} to={item.to(activeLedgerId)} label={item.label} icon={item.icon} />
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
