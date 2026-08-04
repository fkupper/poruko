import { useEffect } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';

import { TooltipProvider } from '@/components/ui/tooltip';
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

/**
 * Redirects to /login when the user is not authenticated.
 */
function ProtectedRoute() {
    const token = useAuthStore((s) => s.token);
    if (!token) return <Navigate to="/login" replace />;
    return <Outlet />;
}

/**
 * Redirects authenticated users away from public-only routes (login, register).
 */
function PublicOnlyRoute() {
    const token = useAuthStore((s) => s.token);
    if (token) return <Navigate to="/" replace />;
    return <Outlet />;
}

/**
 * Runs once on mount to hydrate the user from the persisted token.
 * The 401 interceptor in api/client.ts handles invalid/expired tokens.
 */
function AuthHydration() {
    const setUser = useAuthStore((s) => s.setUser);

    useEffect(() => {
        const token = useAuthStore.getState().token;
        if (!token) return;
        me()
            .then(({ user }) => setUser(user))
            .catch(() => {
                // Handled by the 401 interceptor in api/client.ts
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
                        <Route element={<DashboardLayout />}>
                            <Route path="/" element={<DashboardPage />} />
                            <Route path="/transactions" element={<TransactionsPage />} />
                            <Route path="/settlement" element={<SettlementPage />} />
                            <Route path="/accounts" element={<AccountsPage />} />
                            <Route path="/recurring" element={<RecurringPage />} />
                            <Route path="/my-finance" element={<MyFinancePage />} />
                            <Route path="/settings" element={<SettingsPage />} />
                        </Route>
                    </Route>
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
                </BrowserRouter>
            </TooltipProvider>
        </QueryClientProvider>
    );
}
