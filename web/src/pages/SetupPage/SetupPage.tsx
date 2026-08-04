import { SetupWizard } from '@/features/onboarding/SetupWizard';

export default function SetupPage() {
    return (
        <div className="flex min-h-svh items-center justify-center bg-muted/20 p-4">
            <SetupWizard />
        </div>
    );
}
