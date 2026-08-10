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
 * Normalizes a currency amount string so the decimal separator is `.`.
 * - If both `,` and `.` are present, the last separator is the decimal.
 * - If only `,` is present, it is treated as the decimal separator.
 * - Currency symbols and other non-numeric characters are stripped.
 */
function normalizeAmountString(amountString: string): string {
    // Strip currency symbols / letters / whitespace; keep digits, separators, and sign
    const cleaned = amountString.replace(/[^0-9.,-]/g, '');

    const lastComma = cleaned.lastIndexOf(',');
    const lastDot = cleaned.lastIndexOf('.');

    if (lastComma === -1 && lastDot === -1) {
        return cleaned;
    }

    const decimalIndex = Math.max(lastComma, lastDot);
    const integerPart = cleaned.slice(0, decimalIndex).replace(/[.,]/g, '');
    const fractionalPart = cleaned.slice(decimalIndex + 1).replace(/[.,]/g, '');
    const sign = integerPart.startsWith('-') ? '-' : '';
    const digits = integerPart.replace(/^-/, '');

    return `${sign}${digits}.${fractionalPart}`;
}

/**
 * Parses user currency input string into integer cents.
 * Accepts US ("60.50", "1,234.56") and European ("60,50", "1.234,56") formats.
 * E.g., "60.00" -> 6000, "60" -> 6000, "60,50" -> 6050, "1.234,56" -> 123456
 */
export function currencyToCents(amountString: string): number {
    if (!amountString) return 0;
    const sanitized = normalizeAmountString(amountString);
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
