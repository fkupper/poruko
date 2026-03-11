import './App.css';
import { useEffect, type ReactNode } from 'react';
import { Link, Navigate, Route, Routes, useNavigate, useParams } from 'react-router-dom';
import type { AuthUser } from './api/auth.ts';
import { useHealthQuery } from './api/health.ts';
import { LoginPage } from './features/auth/LoginPage.tsx';
import { RegisterPage } from './features/auth/RegisterPage.tsx';
import { ManageAccountsPage } from './features/ledger/ManageAccountsPage.tsx';
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
    <div className="min-h-screen bg-slate-950 text-slate-50">
      <header className="border-b border-slate-800 bg-slate-900">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
          <Link className="text-lg font-semibold" to="/ledgers/1/accounts">
            Poruko
          </Link>
          <div className="text-sm">
            {isPending && <span className="text-slate-300">API: checking...</span>}
            {isError && <span className="text-red-400">API error: {(error as Error).message}</span>}
            {data && <span className="text-emerald-400">API: {data.status}</span>}
          </div>
          <div className="text-sm">
            {user === null ? (
              <div className="flex items-center gap-2">
                <Link className="text-indigo-400 hover:text-indigo-300" to="/login">
                  Log in
                </Link>
                <Link className="text-indigo-400 hover:text-indigo-300" to="/register">
                  Register
                </Link>
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <span className="text-slate-300">{user.email}</span>
                <button
                  className="rounded border border-slate-700 px-2 py-1 text-slate-200 hover:border-slate-500"
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
    return <p className="mx-auto max-w-5xl px-4 py-8 text-slate-300">Loading account...</p>;
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
    return <p className="mx-auto max-w-5xl px-4 py-8 text-red-400">Invalid ledger id.</p>;
  }

  return <ManageAccountsPage ledgerId={ledgerId} />;
}

export default App;
