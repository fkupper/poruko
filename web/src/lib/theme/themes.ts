export const THEMES = ['poruko', 'neutral', 'quiet', 'neon-tokyo'] as const;
export const COLOR_MODES = ['light', 'dark', 'system'] as const;

export type ThemeId = (typeof THEMES)[number];
export type ColorMode = (typeof COLOR_MODES)[number];
export type ResolvedColorMode = 'light' | 'dark';

export const DEFAULT_THEME: ThemeId = 'poruko';
export const DEFAULT_COLOR_MODE: ColorMode = 'system';

/** Themes that must always render in dark color-scheme (neon on dark only). */
export const DARK_ONLY_THEMES: ReadonlySet<ThemeId> = new Set(['neon-tokyo']);

export const THEME_META: Record<
    ThemeId,
    { label: string; description: string }
> = {
    poruko: {
        label: 'Poruko',
        description: 'Emerald Ethos — calm botanical ledger.',
    },
    neutral: {
        label: 'Neutral',
        description: 'Clean grayscale defaults.',
    },
    quiet: {
        label: 'Quiet',
        description: 'Teal tonal depth with soft surface layers.',
    },
    'neon-tokyo': {
        label: 'Neon Tokyo',
        description: 'Cyberpunk nightscape — pink/cyan accents on dark only.',
    },
};

export function isThemeId(value: unknown): value is ThemeId {
    return typeof value === 'string' && (THEMES as readonly string[]).includes(value);
}

/** Map legacy theme ids stored before renames. */
export function normalizeThemeId(value: unknown): ThemeId {
    if (value === 'quiet-architect') {
        return 'quiet';
    }

    return isThemeId(value) ? value : DEFAULT_THEME;
}

export function isColorMode(value: unknown): value is ColorMode {
    return typeof value === 'string' && (COLOR_MODES as readonly string[]).includes(value);
}

export function getSystemColorMode(): ResolvedColorMode {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return 'light';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function resolveColorMode(colorMode: ColorMode, theme?: ThemeId): ResolvedColorMode {
    if (theme && DARK_ONLY_THEMES.has(theme)) {
        return 'dark';
    }

    if (colorMode === 'system') {
        return getSystemColorMode();
    }

    return colorMode;
}
