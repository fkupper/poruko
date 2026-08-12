import { create } from 'zustand';
import { persist } from 'zustand/middleware';

import type { User } from '@/api/types';
import { applyDocumentTheme } from '@/lib/theme/applyDocumentTheme';
import {
    DEFAULT_COLOR_MODE,
    DEFAULT_THEME,
    isColorMode,
    normalizeThemeId,
    type ColorMode,
    type ThemeId,
} from '@/lib/theme/themes';

interface ThemeState {
    theme: ThemeId;
    colorMode: ColorMode;
    setTheme: (theme: ThemeId) => void;
    setColorMode: (colorMode: ColorMode) => void;
    setAppearance: (appearance: { theme: ThemeId; colorMode: ColorMode }) => void;
    hydrateFromUser: (user: Pick<User, 'theme' | 'color_mode'>) => void;
}

export const useThemeStore = create<ThemeState>()(
    persist(
        (set) => ({
            theme: DEFAULT_THEME,
            colorMode: DEFAULT_COLOR_MODE,
            setTheme: (theme) => {
                set((state) => {
                    applyDocumentTheme(theme, state.colorMode);
                    return { theme };
                });
            },
            setColorMode: (colorMode) => {
                set((state) => {
                    applyDocumentTheme(state.theme, colorMode);
                    return { colorMode };
                });
            },
            setAppearance: ({ theme, colorMode }) => {
                applyDocumentTheme(theme, colorMode);
                set({ theme, colorMode });
            },
            hydrateFromUser: (user) => {
                const theme = normalizeThemeId(user.theme);
                const colorMode = isColorMode(user.color_mode) ? user.color_mode : DEFAULT_COLOR_MODE;
                applyDocumentTheme(theme, colorMode);
                set({ theme, colorMode });
            },
        }),
        {
            name: 'poruko-theme',
            partialize: (state) => ({
                theme: state.theme,
                colorMode: state.colorMode,
            }),
            onRehydrateStorage: () => (state) => {
                if (!state) {
                    return;
                }

                applyDocumentTheme(state.theme, state.colorMode);
            },
        },
    ),
);
