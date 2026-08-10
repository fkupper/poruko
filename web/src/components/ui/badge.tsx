import * as React from 'react';
import { cn } from '@/lib/utils';

export interface BadgeProps extends React.ComponentProps<'div'> {
    variant?: 'default' | 'secondary' | 'destructive' | 'outline' | 'success' | 'warning' | 'info';
}

export function Badge({ className, variant = 'default', ...props }: BadgeProps) {
    const variants = {
        default: 'border-transparent bg-primary text-primary-foreground hover:bg-primary/80',
        secondary: 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80',
        destructive: 'border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80',
        outline: 'text-foreground border-border',
        success: 'border-transparent bg-inflow/15 text-inflow border-inflow/20',
        warning: 'border-transparent bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/20',
        info: 'border-transparent bg-info/15 text-info border-info/20',
    };

    return (
        <div
            className={cn(
                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-hidden focus:ring-2 focus:ring-ring focus:ring-offset-2',
                variants[variant],
                className
            )}
            {...props}
        />
    );
}
