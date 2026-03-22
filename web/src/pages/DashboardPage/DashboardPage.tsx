import { useAuthStore } from '@/stores/authStore';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export default function DashboardPage() {
    const user = useAuthStore((s) => s.user);

    return (
        <div className="flex flex-col gap-4 p-6">
            <Card>
                <CardHeader>
                    <CardTitle>Welcome, {user?.name ?? 'there'}</CardTitle>
                    <CardDescription>
                        Your Poruko space is ready. Use the navigation to manage your ledger.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Skeleton className="aspect-video rounded-xl" />
                        <Skeleton className="aspect-video rounded-xl" />
                        <Skeleton className="aspect-video rounded-xl" />
                    </div>
                    <Skeleton className="mt-4 h-64 rounded-xl" />
                </CardContent>
            </Card>
        </div>
    );
}
