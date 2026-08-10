import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { fetchLedgers } from '@/api/ledgers';
import { Spinner } from '@/components/ui/spinner';

export function SpaceGuard() {
    const location = useLocation();
    const isSetupPage = location.pathname === '/setup';

    const { data: ledgers, isPending, isError } = useQuery({
        queryKey: ['ledgers'],
        queryFn: fetchLedgers,
    });

    // Track whether user ALREADY had spaces when SpaceGuard originally loaded
    const [hadSpacesOnMount, setHadSpacesOnMount] = useState<boolean | null>(null);

    useEffect(() => {
        if (!isPending && hadSpacesOnMount === null) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setHadSpacesOnMount(!isError && Array.isArray(ledgers) && ledgers.length > 0);
        }
    }, [isPending, isError, ledgers, hadSpacesOnMount]);

    if (isPending) {
        return (
            <div className="flex min-h-svh items-center justify-center bg-background p-4">
                <div className="flex flex-col items-center gap-3 text-center">
                    <Spinner className="size-6 text-primary" />
                    <p className="text-sm font-medium text-muted-foreground">Checking space configuration…</p>
                </div>
            </div>
        );
    }

    const hasSpaces = !isError && Array.isArray(ledgers) && ledgers.length > 0;

    // If user has no spaces and tries to access dashboard pages, redirect to /setup
    if (!hasSpaces && !isSetupPage) {
        return <Navigate to="/setup" replace />;
    }

    // Only redirect away from /setup if the user ALREADY had spaces when they arrived
    if (hadSpacesOnMount === true && isSetupPage) {
        return <Navigate to="/" replace />;
    }

    return <Outlet />;
}
