<script setup>
defineProps({
    players: {
        type: Array,
        required: true,
    },
    showHostBadge: {
        type: Boolean,
        default: false,
    },
    showScore: {
        type: Boolean,
        default: false,
    },
    showReadyStatus: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <TransitionGroup name="player-list" tag="div" class="grid gap-3">
        <div
            v-for="p in players"
            :key="p.id"
            class="flex items-center justify-between p-4 bg-gray-100 dark:bg-gray-800/30 rounded-lg border border-gray-200 dark:border-gray-700"
        >
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-accent-500 flex items-center justify-center font-bold shrink-0 text-white">
                    {{ String(p.display_name).slice(0, 1).toUpperCase() }}
                </div>
                <div class="font-medium truncate text-gray-900 dark:text-gray-50">
                    {{ p.display_name }}
                    <span v-if="p.is_me" class="text-accent-500">(You)</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Ready indicator (checkmark) -->
                <svg
                    v-if="showReadyStatus && p.is_ready"
                    class="w-5 h-5 text-blue-400"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                    />
                </svg>

                <span
                    v-if="showHostBadge && p.is_host"
                    class="px-3 py-1 bg-accent-500/20 text-accent-600 dark:text-accent-400 text-sm rounded-full font-medium"
                >
                    Host
                </span>

                <div v-if="showScore" class="font-bold text-accent-600 dark:text-accent-400 tabular-nums">{{ p.score }} pts</div>
            </div>
        </div>
    </TransitionGroup>
</template>
