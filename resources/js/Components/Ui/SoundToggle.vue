<script setup>
import { useSoundSettings } from "../../Composables/useSoundSettings";

const { isSoundEnabled, toggleSound } = useSoundSettings();
</script>

<template>
    <button
        type="button"
        class="flex items-center justify-between w-full px-4 py-3 rounded-lg bg-white/5 hover:bg-white/8 transition"
        @click="toggleSound"
    >
        <span class="text-sm font-medium text-white/80 font-display">Sound Effects</span>
        <span
            class="relative w-11 h-6 rounded-full transition-colors"
            :class="isSoundEnabled ? 'bg-accent-500' : 'bg-white/10'"
        >
            <span
                class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform"
                :class="isSoundEnabled ? 'translate-x-5' : 'translate-x-0'"
            />
        </span>
    </button>
</template>
