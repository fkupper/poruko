import * as React from 'react';
import { cn } from '@/lib/utils';
import { currencyToCents } from '@/lib/currency';
import { Input } from '@/components/ui/input';

export interface CurrencyInputProps extends Omit<React.ComponentProps<'input'>, 'value' | 'onChange'> {
    value?: number | null; // Integer cents
    onCentsChange?: (cents: number | null) => void;
    currencySymbol?: string;
}

export function CurrencyInput({
    value,
    onCentsChange,
    currencySymbol = '€',
    className,
    disabled,
    ...props
}: CurrencyInputProps) {
    // Keep local string for smooth typing experience
    const [displayVal, setDisplayVal] = React.useState<string>(() => {
        if (value == null) return '';
        return (value / 100).toFixed(2);
    });

    // Update display when value prop changes externally
    React.useEffect(() => {
        const currentCents = displayVal === '' ? null : currencyToCents(displayVal);
        if (currentCents !== value) {
            setDisplayVal(value == null ? '' : (value / 100).toFixed(2));
        }
    }, [value, displayVal]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;
        setDisplayVal(val);
        if (val === '') {
            onCentsChange?.(null);
        } else {
            const cents = currencyToCents(val);
            onCentsChange?.(cents);
        }
    };

    const handleBlur = () => {
        if (displayVal === '') return;
        const cents = currencyToCents(displayVal);
        setDisplayVal((cents / 100).toFixed(2));
    };

    return (
        <div className="relative flex items-center w-full">
            <span className="absolute left-3 text-muted-foreground font-mono text-sm pointer-events-none">
                {currencySymbol}
            </span>
            <Input
                type="text"
                inputMode="decimal"
                value={displayVal}
                onChange={handleChange}
                onBlur={handleBlur}
                disabled={disabled}
                className={cn('h-10 w-full pl-8 font-mono', className)}
                {...props}
            />
        </div>
    );
}
