import { usePreferredDark, useStorage } from "@vueuse/core";
import { computed, watchEffect } from "vue";

export function useTheme() {
    const theme = useStorage("theme", "system");
    const prefersDark = usePreferredDark();

    const isDark = computed(
        () => theme.value === "dark" || (theme.value === "system" && prefersDark.value),
    );

    watchEffect(() => {
        document.documentElement.classList.toggle("dark", isDark.value);
    });

    function toggleTheme() {
        theme.value = theme.value === "dark" ? "light" : "dark";
    }

    return { theme, toggleTheme };
}
