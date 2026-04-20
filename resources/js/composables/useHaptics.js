import { ref, computed } from "vue";
import { usePreferredReducedMotion, useStorage } from "@vueuse/core";

export function useHaptics() {
    const prefersReducedMotion = usePreferredReducedMotion();
    const isEnabled = useStorage("breakster-haptics-enabled", true);
    const isSupported = ref("vibrate" in navigator);

    const patterns = {
        light: [10],
        medium: [25],
        heavy: [50, 30, 50],
        error: [100, 50, 100],
    };

    const canVibrate = computed(
        () => isSupported.value && isEnabled.value && prefersReducedMotion.value !== "reduce",
    );

    function vibrate(pattern) {
        if (!canVibrate.value) {
            return;
        }

        navigator.vibrate(patterns[pattern] ?? pattern);
    }

    function toggleHaptics() {
        isEnabled.value = !isEnabled.value;
    }

    function enableHaptics() {
        isEnabled.value = true;
    }

    function disableHaptics() {
        isEnabled.value = false;
    }

    return {
        isSupported,
        isEnabled,
        canVibrate,
        toggleHaptics,
        enableHaptics,
        disableHaptics,
        light: () => vibrate("light"),
        medium: () => vibrate("medium"),
        heavy: () => vibrate("heavy"),
        error: () => vibrate("error"),
    };
}
