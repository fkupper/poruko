import { useEffect } from 'react';

import { applyDocumentTheme } from '@/lib/theme/applyDocumentTheme';
import { useThemeStore } from '@/stores/themeStore';

/**
 * Keeps `system` color mode in sync with OS preference changes.
 */
export function ThemeSync(): null {
    const theme = useThemeStore((s) => s.theme);
    const colorMode = useThemeStore((s) => s.colorMode);

    useEffect(() => {
        applyDocumentTheme(theme, colorMode);

        if (colorMode !== 'system' || typeof window.matchMedia !== 'function') {
            return;
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => applyDocumentTheme(theme, colorMode);

        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, [theme, colorMode]);

    return null;
}
