import * as React from 'react';
import { cn } from '@/lib/utils';
import { currencyToCents } from '@/lib/currency';
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from '@/components/ui/input-group';

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
        <InputGroup data-disabled={disabled || undefined} className={cn(className)}>
            <InputGroupAddon align="inline-start">
                <InputGroupText className="font-mono">{currencySymbol}</InputGroupText>
            </InputGroupAddon>
            <InputGroupInput
                type="text"
                inputMode="decimal"
                value={displayVal}
                onChange={handleChange}
                onBlur={handleBlur}
                disabled={disabled}
                className="font-mono"
                {...props}
            />
        </InputGroup>
    );
}
