import * as React from 'react';
import { cn } from '@/lib/utils';
import { currencyToCents } from '@/lib/currency';

export interface CurrencyInputProps extends Omit<React.ComponentProps<'input'>, 'value' | 'onChange'> {
    value?: number; // Integer cents
    onCentsChange?: (cents: number) => void;
    currencySymbol?: string;
}

export function CurrencyInput({
    value = 0,
    onCentsChange,
    currencySymbol = '€',
    className,
    disabled,
    ...props
}: CurrencyInputProps) {
    // Keep local string for smooth typing experience
    const [displayVal, setDisplayVal] = React.useState<string>(() => {
        return (value / 100).toFixed(2);
    });

    // Update display when value prop changes externally
    React.useEffect(() => {
        const currentCents = currencyToCents(displayVal);
        if (currentCents !== value) {
            setDisplayVal((value / 100).toFixed(2));
        }
    }, [value, displayVal]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;
        setDisplayVal(val);
        const cents = currencyToCents(val);
        onCentsChange?.(cents);
    };

    const handleBlur = () => {
        const cents = currencyToCents(displayVal);
        setDisplayVal((cents / 100).toFixed(2));
    };

    return (
        <div className="relative flex items-center w-full">
            <span className="absolute left-3 text-muted-foreground font-mono text-sm pointer-events-none">
                {currencySymbol}
            </span>
            <input
                type="text"
                inputMode="decimal"
                value={displayVal}
                onChange={handleChange}
                onBlur={handleBlur}
                disabled={disabled}
                className={cn(
                    'h-10 w-full min-w-0 rounded-lg border border-input bg-transparent pl-8 pr-3 py-1 font-mono text-sm transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive',
                    className
                )}
                {...props}
            />
        </div>
    );
}
