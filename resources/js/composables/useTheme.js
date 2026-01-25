import { ref, onMounted, watch } from 'vue';

export function useTheme() {
    const theme = ref('system');

    onMounted(() => {
        const saved = localStorage.getItem('theme') || 'system';
        theme.value = saved;
        applyTheme(saved);

        // Listen for system theme changes
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', () => {
            if (theme.value === 'system') {
                applyTheme('system');
            }
        });
    });

    watch(theme, (newTheme) => {
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    });

    function applyTheme(mode) {
        const isDark =
            mode === 'dark' ||
            (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    function toggleTheme() {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    }

    return { theme, toggleTheme };
}
