import * as React from 'react';
import { useMutation } from '@tanstack/react-query';
import { MonitorIcon, MoonIcon, SunIcon } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldGroup, FieldTitle } from '@/components/ui/field';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { persistAppearance } from '@/lib/theme/persistAppearance';
import {
    COLOR_MODES,
    THEME_META,
    THEMES,
    type ColorMode,
    type ThemeId,
} from '@/lib/theme/themes';
import { useThemeStore } from '@/stores/themeStore';

const COLOR_MODE_META: Record<
    ColorMode,
    { label: string; icon: React.ComponentType<{ className?: string }> }
> = {
    light: { label: 'Light', icon: SunIcon },
    dark: { label: 'Dark', icon: MoonIcon },
    system: { label: 'System', icon: MonitorIcon },
};

interface AppearanceSettingsProps {
    onFeedback: (type: 'success' | 'error', message: string) => void;
}

export function AppearanceSettings({ onFeedback }: AppearanceSettingsProps) {
    const theme = useThemeStore((s) => s.theme);
    const colorMode = useThemeStore((s) => s.colorMode);

    const mutation = useMutation({
        mutationFn: async (next: { theme: ThemeId; colorMode: ColorMode }) => {
            await persistAppearance(next.theme, next.colorMode);
        },
        onSuccess: () => onFeedback('success', 'Appearance saved.'),
        onError: () => onFeedback('error', 'Failed to save appearance.'),
    });

    const updateTheme = (value: string) => {
        if (!value || !(THEMES as readonly string[]).includes(value)) {
            return;
        }

        mutation.mutate({ theme: value as ThemeId, colorMode });
    };

    const updateColorMode = (value: string) => {
        if (!value || !(COLOR_MODES as readonly string[]).includes(value)) {
            return;
        }

        mutation.mutate({ theme, colorMode: value as ColorMode });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Appearance</CardTitle>
                <CardDescription>
                    Choose a theme and color mode. Preferences sync to your account.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <FieldGroup className="flex flex-col gap-6">
                    <Field>
                        <FieldTitle id="color-mode-label">Color mode</FieldTitle>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            spacing={2}
                            value={colorMode}
                            onValueChange={updateColorMode}
                            aria-labelledby="color-mode-label"
                            disabled={mutation.isPending}
                        >
                            {COLOR_MODES.map((mode) => {
                                const Icon = COLOR_MODE_META[mode].icon;
                                return (
                                    <ToggleGroupItem key={mode} value={mode} aria-label={COLOR_MODE_META[mode].label}>
                                        <Icon data-icon="inline-start" />
                                        {COLOR_MODE_META[mode].label}
                                    </ToggleGroupItem>
                                );
                            })}
                        </ToggleGroup>
                    </Field>

                    <Field>
                        <FieldTitle id="theme-label">Theme</FieldTitle>
                        <FieldDescription>Aesthetic pack applied on top of light or dark mode.</FieldDescription>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            spacing={2}
                            orientation="vertical"
                            className="w-full max-w-md"
                            value={theme}
                            onValueChange={updateTheme}
                            aria-labelledby="theme-label"
                            disabled={mutation.isPending}
                        >
                            {THEMES.map((id) => (
                                <ToggleGroupItem
                                    key={id}
                                    value={id}
                                    className="h-auto w-full justify-start px-4 py-3 text-left"
                                    aria-label={THEME_META[id].label}
                                >
                                    <span className="flex flex-col items-start gap-0.5">
                                        <span className="font-medium">{THEME_META[id].label}</span>
                                        <span className="text-xs font-normal text-muted-foreground">
                                            {THEME_META[id].description}
                                        </span>
                                    </span>
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                    </Field>
                </FieldGroup>
            </CardContent>
        </Card>
    );
}
