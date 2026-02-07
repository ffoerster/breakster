<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    playerName: {
        type: String,
        default: null,
    },
    stat: {
        type: String,
        default: null,
    },
    description: {
        type: String,
        default: null,
    },
    variant: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'warning'].includes(v),
    },
});
</script>

<template>
    <div
        v-motion
        :initial="{ scale: 0.9, opacity: 0 }"
        :enter="{ scale: 1, opacity: 1, transition: { type: 'spring', stiffness: 200, damping: 20 } }"
        class="p-5 rounded-xl border"
        :class="
            variant === 'warning'
                ? 'bg-orange-500/10 border-orange-500/30'
                : 'bg-accent-500/10 border-accent-500/30'
        "
    >
        <div class="text-sm font-medium mb-2 font-display" :class="variant === 'warning' ? 'text-orange-300' : 'text-accent-200'">
            {{ title }}
        </div>

        <template v-if="playerName">
            <div class="text-xl font-bold mb-1">{{ playerName }}</div>
            <div v-if="stat" class="text-lg font-mono-party" :class="variant === 'warning' ? 'text-orange-200' : 'text-accent-200'">
                {{ stat }}
            </div>
            <div v-if="description" class="text-sm text-white/60 mt-1 truncate">{{ description }}</div>
        </template>

        <template v-else>
            <div class="text-white/60 italic">No data</div>
        </template>
    </div>
</template>
