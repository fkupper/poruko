import { useEffect } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';

import { TooltipProvider } from '@/components/ui/tooltip';
import { Spinner } from '@/components/ui/spinner';
import { me } from '@/api/auth';
import { queryClient } from '@/lib/queryClient';
import { useAuthStore } from '@/stores/authStore';
import LoginPage from '@/features/auth/LoginPage/LoginPage';
import RegisterPage from '@/features/auth/RegisterPage/RegisterPage';
import DashboardLayout from '@/layouts/DashboardLayout/DashboardLayout';
import DashboardPage from '@/pages/DashboardPage/DashboardPage';
import TransactionsPage from '@/pages/TransactionsPage/TransactionsPage';
import SettlementPage from '@/pages/SettlementPage/SettlementPage';
import AccountsPage from '@/pages/AccountsPage/AccountsPage';
import RecurringPage from '@/pages/RecurringPage/RecurringPage';
import MyFinancePage from '@/pages/MyFinancePage/MyFinancePage';
import SettingsPage from '@/pages/SettingsPage/SettingsPage';
import AccountSettingsPage from '@/pages/AccountSettingsPage/AccountSettingsPage';
import MembersPage from '@/pages/MembersPage/MembersPage';
// import IngestionPage from '@/pages/IngestionPage/IngestionPage';
import SetupPage from '@/pages/SetupPage/SetupPage';
import { SpaceGuard } from '@/routes/SpaceGuard';

/**
 * Redirects to /login when the user is not authenticated.
 * Only a full session `token` counts — pending 2FA alone must stay on /login.
 * While a persisted token is being hydrated via /auth/me, show a spinner.
 */
function ProtectedRoute() {
    const token = useAuthStore((s) => s.token);
    const authStatus = useAuthStore((s) => s.authStatus);

    if (!token) return <Navigate to="/login" replace />;

    if (authStatus === 'hydrating') {
        return (
            <div className="flex min-h-svh items-center justify-center bg-background p-4">
                <div className="flex flex-col items-center gap-3 text-center">
                    <Spinner className="size-6 text-primary" />
                    <p className="text-sm font-medium text-muted-foreground">Loading session…</p>
                </div>
            </div>
        );
    }

    return <Outlet />;
}

/**
 * Redirects authenticated users away from public-only routes (login, register).
 * Only a full session `token` counts — pending 2FA alone must stay on /login.
 */
function PublicOnlyRoute() {
    const token = useAuthStore((s) => s.token);
    if (token) return <Navigate to="/" replace />;
    return <Outlet />;
}

/**
 * Runs once on mount to hydrate the user from the persisted token.
 * The 401 interceptor in api/client.ts handles invalid/expired tokens.
 * Intentionally ignores pendingTwoFactorToken so /auth/me is never called mid-challenge.
 */
function AuthHydration() {
    const setUser = useAuthStore((s) => s.setUser);
    const setAuthStatus = useAuthStore((s) => s.setAuthStatus);

    useEffect(() => {
        const token = useAuthStore.getState().token;
        if (!token) {
            setAuthStatus('anonymous');
            return;
        }

        setAuthStatus('hydrating');
        me()
            .then(({ user }) => setUser(user))
            .catch(() => {
                // Handled by the 401 interceptor in api/client.ts
                // If not 401, still unblock shell with anonymous-ready failure path
                if (useAuthStore.getState().token) {
                    setAuthStatus('ready');
                }
            });
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return null;
}

export default function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <TooltipProvider>
                <BrowserRouter>
                <AuthHydration />
                <Routes>
                    <Route element={<PublicOnlyRoute />}>
                        <Route path="/login" element={<LoginPage />} />
                        <Route path="/register" element={<RegisterPage />} />
                    </Route>
                    <Route element={<ProtectedRoute />}>
                        <Route element={<SpaceGuard />}>
                            <Route path="/setup" element={<SetupPage />} />
                            <Route element={<DashboardLayout />}>
                                <Route path="/" element={<DashboardPage />} />
                                <Route path="/transactions" element={<TransactionsPage />} />
                                <Route path="/settlement" element={<SettlementPage />} />
                                <Route path="/accounts" element={<AccountsPage />} />
                                <Route path="/recurring" element={<RecurringPage />} />
                                <Route path="/my-finance" element={<MyFinancePage />} />
                                {/* Hidden until AI ingestion ships */}
                                {/* <Route path="/ingestion" element={<IngestionPage />} /> */}
                                <Route path="/account" element={<AccountSettingsPage />} />
                                <Route path="/settings" element={<SettingsPage />} />
                                <Route path="/members" element={<MembersPage />} />
                            </Route>
                        </Route>
                    </Route>
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
                </BrowserRouter>
            </TooltipProvider>
        </QueryClientProvider>
    );
}
