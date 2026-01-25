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
            class="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg"
        >
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center font-bold shrink-0">
                    {{ String(p.display_name).slice(0, 1).toUpperCase() }}
                </div>
                <div class="font-medium truncate">
                    {{ p.display_name }}
                    <span v-if="p.is_me" class="text-purple-400">(You)</span>
                </div>
            </div>

            <span
                v-if="showHostBadge && p.is_host"
                class="px-3 py-1 bg-yellow-500/20 text-yellow-400 text-sm rounded-full"
            >
                Host
            </span>

            <div v-else-if="showScore" class="font-bold text-purple-300">{{ p.score }} pts</div>
        </div>
    </div>
</template>
