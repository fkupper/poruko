import './App.css';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Navigate, Route, Routes, useLocation, useNavigate, useParams } from 'react-router-dom';
import type { AuthUser } from './api/auth.ts';
import { useHealthQuery } from './api/health.ts';
import { useLedgersQuery } from './api/ledgers.ts';
import { LoginPage } from './features/auth/LoginPage.tsx';
import { RegisterPage } from './features/auth/RegisterPage.tsx';
import { LedgerSidebar, MobileLedgerBar, type LedgerOption } from './features/layout/LedgerSidebar.tsx';
import { ManageAccountsPage } from './features/ledger/ManageAccountsPage.tsx';
import { LedgerDashboardPage } from './features/ledger/LedgerDashboardPage.tsx';
import { MyFinancesPage } from './features/ledger/MyFinancesPage.tsx';
import { useAppStore } from './stores/appStore.ts';
import { useAuthStore } from './stores/authStore.ts';

function App() {
  const { data, isPending, isError, error } = useHealthQuery();
  const user = useAuthStore((state) => state.user);
  const isBootstrapping = useAuthStore((state) => state.isBootstrapping);
  const hydrateToken = useAuthStore((state) => state.hydrateToken);
  const fetchMe = useAuthStore((state) => state.fetchMe);
  const logout = useAuthStore((state) => state.logout);
  const recentLedgerIds = useAppStore((state) => state.recentLedgerIds);
  const setCurrentLedgerId = useAppStore((state) => state.setCurrentLedgerId);
  const navigate = useNavigate();
  const location = useLocation();
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  const { data: ledgers } = useLedgersQuery(user !== null);

  const activeLedgerId = useMemo(() => {
    const match = location.pathname.match(/^\/ledgers\/(\d+)/);
    if (match === null) {
      return null;
    }

    const ledgerId = Number(match[1]);
    if (!Number.isInteger(ledgerId) || ledgerId <= 0) {
      return null;
    }

    return ledgerId;
  }, [location.pathname]);

  const ledgerOptions = useMemo<LedgerOption[]>(() => {
    if (ledgers === undefined || ledgers.length === 0) {
      return [];
    }

    const byId = new Map(ledgers.map((ledger) => [ledger.id, ledger]));

    const orderedIds = [
      ...(activeLedgerId !== null ? [activeLedgerId] : []),
      ...recentLedgerIds,
      ...ledgers.map((ledger) => ledger.id),
    ].filter((ledgerId, index, ids): ledgerId is number => {
      return byId.has(ledgerId) && ids.indexOf(ledgerId) === index;
    });

    return orderedIds.flatMap((ledgerId) => {
      const ledger = byId.get(ledgerId);
      if (ledger === undefined) {
        return [];
      }

      const membersCount = ledger.users_count;
      const membersLabel = membersCount !== undefined ? `${membersCount} members` : 'Shared space';

      return [
        {
          id: ledger.id,
          name: ledger.name,
          membersLabel,
        },
      ];
    });
  }, [activeLedgerId, ledgers, recentLedgerIds]);

  const apiStatusMessage = useMemo(() => {
    if (isPending) {
      return 'API: checking...';
    }

    if (isError) {
      return `API error: ${(error as Error).message}`;
    }

    if (data !== undefined) {
      return `API: ${data.status}`;
    }

    return 'API: unknown';
  }, [data, error, isError, isPending]);
  const hasLedgers = ledgerOptions.length > 0;
  const defaultLedgerId = activeLedgerId ?? (hasLedgers ? ledgerOptions[0]?.id : null);

  useEffect(() => {
    hydrateToken();
    void fetchMe();
  }, [fetchMe, hydrateToken]);

  useEffect(() => {
    setCurrentLedgerId(activeLedgerId);
  }, [activeLedgerId, setCurrentLedgerId]);

  async function handleLogout(): Promise<void> {
    setIsLoggingOut(true);
    try {
      await logout();
      navigate('/login', { replace: true });
    } finally {
      setIsLoggingOut(false);
    }
  }

  function handleLedgerChange(ledgerId: number): void {
    navigate(`/ledgers/${ledgerId}/dashboard`);
  }

  return (
    <div className="min-h-screen bg-background text-foreground">
      {user !== null && (
        <LedgerSidebar
          activeLedgerId={activeLedgerId}
          apiStatus={apiStatusMessage}
          isLoggingOut={isLoggingOut}
          ledgers={ledgerOptions}
          onLedgerChange={handleLedgerChange}
          onLogout={() => void handleLogout()}
          userEmail={user.email}
        />
      )}

      <div className={user !== null ? 'min-h-screen md:pl-64' : 'min-h-screen'}>
        {user !== null && (
          <MobileLedgerBar activeLedgerId={activeLedgerId} ledgers={ledgerOptions} onLedgerChange={handleLedgerChange} />
        )}

        <Routes>
          <Route
            element={
              <RequireAuth isBootstrapping={isBootstrapping} user={user}>
                {hasLedgers ? (
                  <Navigate replace to={`/ledgers/${defaultLedgerId}/dashboard`} />
                ) : (
                  <p className="mx-auto max-w-5xl px-4 py-8 text-muted-foreground">
                    No spaces available yet. Create a ledger in the API or seed data to get started.
                  </p>
                )}
              </RequireAuth>
            }
            path="/"
          />
          <Route
            element={
              <RedirectIfAuthenticated defaultLedgerId={defaultLedgerId} user={user}>
                <LoginPage />
              </RedirectIfAuthenticated>
            }
            path="/login"
          />
          <Route
            element={
              <RedirectIfAuthenticated defaultLedgerId={defaultLedgerId} user={user}>
                <RegisterPage />
              </RedirectIfAuthenticated>
            }
            path="/register"
          />
          <Route
            element={
              <RequireAuth isBootstrapping={isBootstrapping} user={user}>
                <LedgerDashboardRoute />
              </RequireAuth>
            }
            path="/ledgers/:ledgerId/dashboard"
          />
          <Route
            element={
              <RequireAuth isBootstrapping={isBootstrapping} user={user}>
                <LedgerAccountsRoute />
              </RequireAuth>
            }
            path="/ledgers/:ledgerId/accounts"
          />
          <Route
            element={
              <RequireAuth isBootstrapping={isBootstrapping} user={user}>
                <LedgerMyFinancesRoute />
              </RequireAuth>
            }
            path="/ledgers/:ledgerId/my-finances"
          />
        </Routes>
      </div>
    </div>
  );
}

interface RequireAuthProps {
  children: ReactNode;
  user: AuthUser | null;
  isBootstrapping: boolean;
}

function RequireAuth({ children, user, isBootstrapping }: RequireAuthProps) {
  if (isBootstrapping) {
    return <p className="mx-auto max-w-5xl px-4 py-8 text-muted-foreground">Loading account...</p>;
  }

  if (user === null) {
    return <Navigate replace to="/login" />;
  }

  return children;
}

interface RedirectIfAuthenticatedProps {
  children: ReactNode;
  user: AuthUser | null;
  defaultLedgerId: number | null;
}

function RedirectIfAuthenticated({ children, user, defaultLedgerId }: RedirectIfAuthenticatedProps) {
  if (user !== null) {
    if (defaultLedgerId === null) {
      return (
        <p className="mx-auto max-w-5xl px-4 py-8 text-muted-foreground">
          You are signed in but do not have any spaces yet. Create a ledger in the API or ask an admin for an invite.
        </p>
      );
    }

    return <Navigate replace to={`/ledgers/${defaultLedgerId}/dashboard`} />;
  }

  return children;
}

function LedgerDashboardRoute() {
  const params = useParams<{ ledgerId: string }>();
  const ledgerId = Number(params.ledgerId ?? '0');

  if (!Number.isInteger(ledgerId) || ledgerId <= 0) {
    return <p className="mx-auto max-w-5xl px-4 py-8 text-destructive">Invalid space id.</p>;
  }

  return <LedgerDashboardPage ledgerId={ledgerId} />;
}

function LedgerAccountsRoute() {
  const params = useParams<{ ledgerId: string }>();
  const ledgerId = Number(params.ledgerId ?? '0');

  if (!Number.isInteger(ledgerId) || ledgerId <= 0) {
    return <p className="mx-auto max-w-5xl px-4 py-8 text-destructive">Invalid space id.</p>;
  }

  return <ManageAccountsPage ledgerId={ledgerId} />;
}

function LedgerMyFinancesRoute() {
  const params = useParams<{ ledgerId: string }>();
  const user = useAuthStore((state) => state.user);
  const ledgerId = Number(params.ledgerId ?? '0');

  if (!Number.isInteger(ledgerId) || ledgerId <= 0) {
    return <p className="mx-auto max-w-5xl px-4 py-8 text-destructive">Invalid space id.</p>;
  }

  if (user === null) {
    return <Navigate replace to="/login" />;
  }

  return <MyFinancesPage ledgerId={ledgerId} userId={user.id} />;
}

export default App;
