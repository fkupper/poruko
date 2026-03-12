import './App.css';
import { useEffect, type ReactNode } from 'react';
import { Link, Navigate, Route, Routes, useNavigate, useParams } from 'react-router-dom';
import type { AuthUser } from './api/auth.ts';
import { useHealthQuery } from './api/health.ts';
import { LoginPage } from './features/auth/LoginPage.tsx';
import { RegisterPage } from './features/auth/RegisterPage.tsx';
import { ManageAccountsPage } from './features/ledger/ManageAccountsPage.tsx';
import { MyFinancesPage } from './features/ledger/MyFinancesPage.tsx';
import { useAuthStore } from './stores/authStore.ts';

function App() {
  const { data, isPending, isError, error } = useHealthQuery();
  const user = useAuthStore((state) => state.user);
  const isBootstrapping = useAuthStore((state) => state.isBootstrapping);
  const hydrateToken = useAuthStore((state) => state.hydrateToken);
  const fetchMe = useAuthStore((state) => state.fetchMe);
  const logout = useAuthStore((state) => state.logout);
  const navigate = useNavigate();

  useEffect(() => {
    hydrateToken();
    void fetchMe();
  }, [fetchMe, hydrateToken]);

  async function handleLogout(): Promise<void> {
    await logout();
    navigate('/login', { replace: true });
  }

  return (
    <div className="min-h-screen bg-background text-foreground">
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
          <div className="flex items-center gap-4">
            <Link className="text-lg font-semibold" to="/ledgers/1/accounts">
              Poruko
            </Link>
            {user !== null && (
              <nav className="flex items-center gap-3 text-sm">
                <Link className="text-muted-foreground hover:text-foreground" to="/ledgers/1/accounts">
                  Accounts
                </Link>
                <Link className="text-muted-foreground hover:text-foreground" to="/ledgers/1/my-finances">
                  My Finances
                </Link>
              </nav>
            )}
          </div>
          <div className="text-sm">
            {isPending && <span className="text-muted-foreground">API: checking...</span>}
            {isError && <span className="text-destructive">API error: {(error as Error).message}</span>}
            {data && <span className="text-inflow">API: {data.status}</span>}
          </div>
          <div className="text-sm">
            {user === null ? (
              <div className="flex items-center gap-2">
                <Link className="text-info hover:opacity-80" to="/login">
                  Log in
                </Link>
                <Link className="text-info hover:opacity-80" to="/register">
                  Register
                </Link>
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <span className="text-muted-foreground">{user.email}</span>
                <button
                  className="rounded-control border border-border px-2 py-1 text-foreground hover:opacity-80"
                  onClick={() => void handleLogout()}
                  type="button"
                >
                  Log out
                </button>
              </div>
            )}
          </div>
        </div>
      </header>

      <Routes>
        <Route
          element={
            <RequireAuth isBootstrapping={isBootstrapping} user={user}>
              <Navigate replace to="/ledgers/1/accounts" />
            </RequireAuth>
          }
          path="/"
        />
        <Route
          element={
            <RedirectIfAuthenticated user={user}>
              <LoginPage />
            </RedirectIfAuthenticated>
          }
          path="/login"
        />
        <Route
          element={
            <RedirectIfAuthenticated user={user}>
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
}

function RedirectIfAuthenticated({ children, user }: RedirectIfAuthenticatedProps) {
  if (user !== null) {
    return <Navigate replace to="/ledgers/1/accounts" />;
  }

  return children;
}

function LedgerAccountsRoute() {
  const params = useParams<{ ledgerId: string }>();
  const ledgerId = Number(params.ledgerId ?? '0');

  if (!Number.isInteger(ledgerId) || ledgerId <= 0) {
    return <p className="mx-auto max-w-5xl px-4 py-8 text-destructive">Invalid ledger id.</p>;
  }

  return <ManageAccountsPage ledgerId={ledgerId} />;
}

function LedgerMyFinancesRoute() {
  const params = useParams<{ ledgerId: string }>();
  const user = useAuthStore((state) => state.user);
  const ledgerId = Number(params.ledgerId ?? '0');

  if (!Number.isInteger(ledgerId) || ledgerId <= 0) {
    return <p className="mx-auto max-w-5xl px-4 py-8 text-destructive">Invalid ledger id.</p>;
  }

  if (user === null) {
    return <Navigate replace to="/login" />;
  }

  return <MyFinancesPage ledgerId={ledgerId} userId={user.id} />;
}

export default App;
