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
});
</script>

<template>
    <div class="grid gap-3">
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

            <span
                v-if="showHostBadge && p.is_host"
                class="px-3 py-1 bg-accent-500/20 text-accent-600 dark:text-accent-400 text-sm rounded-full font-medium"
            >
                Host
            </span>

            <div v-else-if="showScore" class="font-bold text-accent-600 dark:text-accent-400">{{ p.score }} pts</div>
        </div>
    </div>
</template>
