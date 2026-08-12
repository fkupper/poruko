import { updateAppearance } from '@/api/auth';
import type { ColorMode, ThemeId } from '@/lib/theme/themes';
import { useAuthStore } from '@/stores/authStore';
import { useThemeStore } from '@/stores/themeStore';

/**
 * Apply appearance locally, then persist to the user account when authenticated.
 * Reverts the local store on API failure.
 */
export async function persistAppearance(theme: ThemeId, colorMode: ColorMode): Promise<void> {
    const previous = {
        theme: useThemeStore.getState().theme,
        colorMode: useThemeStore.getState().colorMode,
    };

    useThemeStore.getState().setAppearance({ theme, colorMode });

    const token = useAuthStore.getState().token;
    if (!token) {
        return;
    }

    try {
        const { user } = await updateAppearance({ theme, color_mode: colorMode });
        // Avoid re-entry loops: update user without relying on theme hydrate side effects.
        useAuthStore.setState({ user, authStatus: 'ready' });
    } catch (error) {
        useThemeStore.getState().setAppearance(previous);
        throw error;
    }
}
