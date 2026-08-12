import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { applyDocumentTheme } from '@/lib/theme/applyDocumentTheme';
import { getSystemColorMode, resolveColorMode } from '@/lib/theme/themes';

describe('resolveColorMode', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('forces dark for neon-tokyo regardless of color mode', () => {
        expect(resolveColorMode('light', 'neon-tokyo')).toBe('dark');
        expect(resolveColorMode('system', 'neon-tokyo')).toBe('dark');
    });

    it('returns light and dark directly for normal themes', () => {
        expect(resolveColorMode('light')).toBe('light');
        expect(resolveColorMode('dark')).toBe('dark');
    });

    it('resolves system from prefers-color-scheme', () => {
        vi.stubGlobal(
            'matchMedia',
            vi.fn().mockImplementation((query: string) => ({
                matches: query.includes('dark'),
                media: query,
                addEventListener: vi.fn(),
                removeEventListener: vi.fn(),
            })),
        );

        expect(getSystemColorMode()).toBe('dark');
        expect(resolveColorMode('system')).toBe('dark');
    });
});

describe('applyDocumentTheme', () => {
    beforeEach(() => {
        document.documentElement.className = '';
        delete document.documentElement.dataset.theme;
        document.documentElement.style.colorScheme = '';
    });

    it('sets data-theme and dark class for dark mode', () => {
        applyDocumentTheme('quiet', 'dark');

        expect(document.documentElement.dataset.theme).toBe('quiet');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.style.colorScheme).toBe('dark');
    });

    it('forces dark class for neon-tokyo even when color mode is light', () => {
        applyDocumentTheme('neon-tokyo', 'light');

        expect(document.documentElement.dataset.theme).toBe('neon-tokyo');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.style.colorScheme).toBe('dark');
    });

    it('clears dark class for light mode', () => {
        document.documentElement.classList.add('dark');

        applyDocumentTheme('neutral', 'light');

        expect(document.documentElement.dataset.theme).toBe('neutral');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.style.colorScheme).toBe('light');
    });
});
