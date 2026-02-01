import { useStorage } from '@vueuse/core';
import { computed } from 'vue';

export function useSoundSettings() {
    const soundEnabled = useStorage('breakster-sound-enabled', true);

    const isSoundEnabled = computed(() => soundEnabled.value);

    function toggleSound() {
        soundEnabled.value = !soundEnabled.value;
    }

    function enableSound() {
        soundEnabled.value = true;
    }

    function disableSound() {
        soundEnabled.value = false;
    }

    return {
        isSoundEnabled,
        soundEnabled,
        toggleSound,
        enableSound,
        disableSound,
    };
}
