<script setup>
defineProps({
    providers: {
        type: Array,
        required: true,
    },
    selectedProviderId: {
        type: [Number, String, null],
        default: null,
    },
    isHost: {
        type: Boolean,
        required: true,
    },
    onSelect: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <div class="grid grid-cols-2 gap-4">
        <button
            v-for="provider in providers"
            :key="provider.id"
            type="button"
            class="relative p-4 rounded-lg border-2 transition-all"
            :disabled="!isHost || selectedProviderId === provider.id"
            :class="
                selectedProviderId === provider.id
                    ? 'border-accent-500 bg-accent-500/20'
                    : isHost
                        ? 'border-gray-600 bg-gray-700/30 hover:border-gray-500 hover:bg-gray-700/50'
                        : 'border-gray-700 bg-gray-800/30 opacity-60 cursor-not-allowed'
            "
            @click="onSelect(provider.id)"
        >
            <div class="text-lg font-bold" :class="selectedProviderId === provider.id ? 'text-accent-400' : 'text-white'">
                {{ provider.name }}
            </div>
            <div class="text-sm text-gray-400 mt-1">Players open tracks in {{ provider.name }}</div>
        </button>
    </div>
</template>
