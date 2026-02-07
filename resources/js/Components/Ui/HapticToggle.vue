<script setup>
import { useHaptics } from "../../Composables/useHaptics";

const { isSupported, isEnabled, toggleHaptics } = useHaptics();
</script>

<template>
    <button
        type="button"
        class="flex items-center justify-between w-full px-4 py-3 rounded-lg bg-gray-800/50 hover:bg-gray-800/70 transition"
        :disabled="!isSupported"
        @click="toggleHaptics"
    >
        <span class="text-sm font-medium text-gray-200">
            Haptic Feedback
            <span v-if="!isSupported" class="text-xs text-gray-500 ml-1">(Not supported)</span>
        </span>
        <span
            class="relative w-11 h-6 rounded-full transition-colors"
            :class="isSupported && isEnabled ? 'bg-accent-500' : 'bg-gray-600'"
        >
            <span
                class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform"
                :class="isSupported && isEnabled ? 'translate-x-5' : 'translate-x-0'"
            />
        </span>
    </button>
</template>
