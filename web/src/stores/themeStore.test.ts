import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { useThemeStore } from '@/stores/themeStore';

describe('useThemeStore', () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.className = '';
        delete document.documentElement.dataset.theme;
        useThemeStore.setState({ theme: 'poruko', colorMode: 'system' });
    });

    afterEach(() => {
        localStorage.clear();
    });

    it('applies theme and color mode to the document', () => {
        const { result } = renderHook(() => useThemeStore());

        act(() => {
            result.current.setAppearance({ theme: 'quiet', colorMode: 'dark' });
        });

        expect(result.current.theme).toBe('quiet');
        expect(result.current.colorMode).toBe('dark');
        expect(document.documentElement.dataset.theme).toBe('quiet');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('hydrates from user preferences', () => {
        const { result } = renderHook(() => useThemeStore());

        act(() => {
            result.current.hydrateFromUser({
                theme: 'quiet',
                color_mode: 'light',
            });
        });

        expect(result.current.theme).toBe('quiet');
        expect(result.current.colorMode).toBe('light');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('falls back to defaults for unknown user values', () => {
        const { result } = renderHook(() => useThemeStore());

        act(() => {
            result.current.hydrateFromUser({
                theme: 'neon-spreadsheet' as 'neutral',
                color_mode: 'auto' as 'system',
            });
        });

        expect(result.current.theme).toBe('poruko');
        expect(result.current.colorMode).toBe('system');
    });
});
