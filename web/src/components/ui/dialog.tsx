import * as React from 'react';
import { cn } from '@/lib/utils';
import { XIcon } from 'lucide-react';

interface DialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    children: React.ReactNode;
}

export function Dialog({ open, onOpenChange, children }: DialogProps) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity animate-in fade-in-0"
                onClick={() => onOpenChange(false)}
            />
            {/* Modal Body */}
            <div className="relative z-50 w-full max-w-lg rounded-xl border bg-background p-6 shadow-lg animate-in zoom-in-95 sm:max-w-xl">
                {children}
            </div>
        </div>
    );
}

export function DialogHeader({ className, children, ...props }: React.ComponentProps<'div'>) {
    return <div className={cn('flex flex-col space-y-1.5 text-center sm:text-left mb-4', className)} {...props}>{children}</div>;
}

export function DialogTitle({ className, children, ...props }: React.ComponentProps<'h2'>) {
    return <h2 className={cn('text-lg font-semibold tracking-tight text-foreground', className)} {...props}>{children}</h2>;
}

export function DialogFooter({ className, children, ...props }: React.ComponentProps<'div'>) {
    return <div className={cn('flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2 gap-2 mt-6', className)} {...props}>{children}</div>;
}

export function DialogClose({ onClose }: { onClose: () => void }) {
    return (
        <button
            type="button"
            onClick={onClose}
            className="absolute right-4 top-4 rounded-sm opacity-70 transition-opacity hover:opacity-100 focus:outline-hidden disabled:pointer-events-none"
        >
            <XIcon className="h-4 w-4" />
            <span className="sr-only">Close</span>
        </button>
    );
}
