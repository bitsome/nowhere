import { computed } from 'vue';

export function useAppLayoutTheme(theme) {
    const resolvedTheme = computed(() => theme?.value ?? 'system');

    const isDark = computed(() => resolvedTheme.value === 'dark');

    return {
        isDark,
        resolvedTheme,
    };
}
