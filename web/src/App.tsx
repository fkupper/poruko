import './App.css';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Navigate, Route, Routes, useLocation, useNavigate, useParams } from 'react-router-dom';
import type { AuthUser } from './api/auth.ts';
import { useHealthQuery } from './api/health.ts';
import { LoginPage } from './features/auth/LoginPage.tsx';
import { RegisterPage } from './features/auth/RegisterPage.tsx';
import { LedgerSidebar, MobileLedgerBar, type LedgerOption } from './features/layout/LedgerSidebar.tsx';
import { ManageAccountsPage } from './features/ledger/ManageAccountsPage.tsx';
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
    const uniqueLedgerIds = [activeLedgerId, ...recentLedgerIds].filter(
      (ledgerId, index, ids): ledgerId is number =>
        ledgerId !== null &&
        Number.isInteger(ledgerId) &&
        ledgerId > 0 &&
        ids.indexOf(ledgerId) === index,
    );

    return uniqueLedgerIds.map((ledgerId) => ({
      id: ledgerId,
      name: `Space #${ledgerId}`,
      membersLabel: 'Shared space',
    }));
  }, [activeLedgerId, recentLedgerIds]);

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
  const defaultLedgerId = activeLedgerId ?? recentLedgerIds[0] ?? 1;

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
    navigate(`/ledgers/${ledgerId}/accounts`);
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
                <Navigate replace to={`/ledgers/${defaultLedgerId}/accounts`} />
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
  defaultLedgerId: number;
}

function RedirectIfAuthenticated({ children, user, defaultLedgerId }: RedirectIfAuthenticatedProps) {
  if (user !== null) {
    return <Navigate replace to={`/ledgers/${defaultLedgerId}/accounts`} />;
  }

  return children;
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
