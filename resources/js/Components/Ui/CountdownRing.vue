<script setup>
import { computed } from 'vue';

const props = defineProps({
    remaining: {
        type: Number,
        required: true,
    },
    total: {
        type: Number,
        default: 3,
    },
});

const progress = computed(() => {
    if (props.total <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(1, props.remaining / props.total));
});

const circumference = 2 * Math.PI * 42;
const strokeDashoffset = computed(() => circumference * (1 - progress.value));
const isUrgent = computed(() => props.remaining <= 1 && props.remaining > 0);
</script>

<template>
    <div
        class="relative w-20 h-20"
        :class="{ 'animate-pulse': isUrgent }"
    >
        <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
            <circle
                cx="50"
                cy="50"
                r="42"
                fill="none"
                stroke="currentColor"
                stroke-width="6"
                class="text-gray-700/40"
            />
            <circle
                cx="50"
                cy="50"
                r="42"
                fill="none"
                stroke="currentColor"
                stroke-width="6"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="strokeDashoffset"
                class="transition-[stroke-dashoffset] duration-200 ease-linear"
                :class="isUrgent ? 'text-red-500' : 'text-accent-500'"
            />
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
            <span
                v-motion
                :initial="{ scale: 1.3, opacity: 0 }"
                :enter="{ scale: 1, opacity: 1, transition: { type: 'spring', stiffness: 300, damping: 20 } }"
                :key="remaining"
                class="text-2xl font-bold tabular-nums"
                :class="isUrgent ? 'text-red-400' : 'text-accent-400'"
            >
                {{ remaining }}
            </span>
        </div>
    </div>
</template>
