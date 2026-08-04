/**
 * Converts integer cents to a formatted currency string.
 * E.g., 6000 -> "€60.00", -67000 -> "-€670.00"
 */
export function centsToCurrency(cents: number | null | undefined, currencySymbol = '€'): string {
    if (cents == null || isNaN(cents)) return `${currencySymbol}0.00`;
    const absCents = Math.abs(cents);
    const dollars = (absCents / 100).toFixed(2);
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(parseFloat(dollars));

    return cents < 0 ? `-${currencySymbol}${formatted}` : `${currencySymbol}${formatted}`;
}

/**
 * Parses user currency input string into integer cents.
 * E.g., "60.00" -> 6000, "60" -> 6000, "670.50" -> 67050
 */
export function currencyToCents(amountString: string): number {
    if (!amountString) return 0;
    // Remove currency symbols and whitespace, allow standard numbers and optional decimal
    const sanitized = amountString.replace(/[^0-9.-]/g, '');
    const floatVal = parseFloat(sanitized);
    if (isNaN(floatVal)) return 0;
    return Math.round(floatVal * 100);
}

/**
 * Formats a decimal ratio into a percentage string.
 * E.g., 0.6 -> "60%", 0.333 -> "33.3%"
 */
export function formatPercent(ratio: number): string {
    if (isNaN(ratio)) return '0%';
    const pct = ratio * 100;
    return pct % 1 === 0 ? `${pct.toFixed(0)}%` : `${pct.toFixed(1)}%`;
}
