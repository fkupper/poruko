import { describe, expect, it } from 'vitest';

import { centsToCurrency, currencyToCents, formatPercent } from './currency';

describe('currencyToCents', () => {
    it('parses plain integers as whole units', () => {
        expect(currencyToCents('60')).toBe(6000);
        expect(currencyToCents('0')).toBe(0);
    });

    it('parses US-style decimals', () => {
        expect(currencyToCents('60.50')).toBe(6050);
        expect(currencyToCents('60.00')).toBe(6000);
        expect(currencyToCents('1,234.56')).toBe(123456);
    });

    it('parses European-style decimals with comma', () => {
        expect(currencyToCents('60,50')).toBe(6050);
        expect(currencyToCents('1.234,56')).toBe(123456);
    });

    it('strips currency symbols', () => {
        expect(currencyToCents('€60.50')).toBe(6050);
        expect(currencyToCents('$1,234.56')).toBe(123456);
        expect(currencyToCents('€1.234,56')).toBe(123456);
    });

    it('returns 0 for empty or invalid input', () => {
        expect(currencyToCents('')).toBe(0);
        expect(currencyToCents('abc')).toBe(0);
    });

    it('rounds to nearest cent', () => {
        expect(currencyToCents('10.005')).toBe(1001);
        expect(currencyToCents('10.004')).toBe(1000);
    });
});

describe('centsToCurrency', () => {
    it('formats with the default euro symbol', () => {
        expect(centsToCurrency(6000)).toBe('€60.00');
        expect(centsToCurrency(-67000)).toBe('-€670.00');
    });

    it('accepts a custom symbol', () => {
        expect(centsToCurrency(1234, '$')).toBe('$12.34');
    });
});

describe('formatPercent', () => {
    it('formats whole and fractional percentages', () => {
        expect(formatPercent(0.6)).toBe('60%');
        expect(formatPercent(0.333)).toBe('33.3%');
    });
});
