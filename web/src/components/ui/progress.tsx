import * as React from 'react';

import { cn } from '@/lib/utils';

function Progress({
    className,
    value,
    ...props
}: React.ComponentProps<'div'> & { value?: number | null }) {
    const bounded = value == null ? null : Math.min(100, Math.max(0, value));

    return (
        <div
            data-slot="progress"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={bounded ?? undefined}
            className={cn('relative h-1.5 w-full overflow-hidden rounded-full bg-muted', className)}
            {...props}
        >
            <div
                data-slot="progress-indicator"
                className={cn(
                    'h-full bg-primary transition-all',
                    bounded == null && 'w-1/3 animate-pulse',
                )}
                style={bounded == null ? undefined : { width: `${bounded}%` }}
            />
        </div>
    );
}

export { Progress };
