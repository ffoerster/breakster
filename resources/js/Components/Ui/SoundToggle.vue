<script setup>
import { useSoundSettings } from "../../Composables/useSoundSettings";

const { isSoundEnabled, toggleSound } = useSoundSettings();
</script>

<template>
    <button
        type="button"
        class="flex items-center justify-between w-full px-4 py-3 rounded-lg bg-gray-800/50 hover:bg-gray-800/70 transition"
        @click="toggleSound"
    >
        <span class="text-sm font-medium text-gray-200">Sound Effects</span>
        <span
            class="relative w-11 h-6 rounded-full transition-colors"
            :class="isSoundEnabled ? 'bg-accent-500' : 'bg-gray-600'"
        >
            <span
                class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform"
                :class="isSoundEnabled ? 'translate-x-5' : 'translate-x-0'"
            />
        </span>
    </button>
</template>
