import {
    type ColorMode,
    type ThemeId,
    resolveColorMode,
} from '@/lib/theme/themes';

export function applyDocumentTheme(theme: ThemeId, colorMode: ColorMode): void {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    const resolved = resolveColorMode(colorMode, theme);

    root.dataset.theme = theme;
    root.classList.toggle('dark', resolved === 'dark');
    root.style.colorScheme = resolved;
}
