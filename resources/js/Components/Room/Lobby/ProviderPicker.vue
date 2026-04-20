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
                    ? 'border-accent-400/70 bg-accent-500/15 shadow-lg shadow-black/20'
                    : isHost
                      ? 'border-accent-500/15 bg-white/5 hover:border-accent-500/30 hover:bg-white/8'
                      : 'border-accent-500/10 bg-white/4 opacity-60 cursor-not-allowed'
            "
            @click="onSelect(provider.id)"
        >
            <div
                class="text-lg font-bold"
                :class="selectedProviderId === provider.id ? 'text-accent-200' : 'text-white'"
            >
                {{ provider.name }}
            </div>
            <div class="text-sm text-white/65 mt-1">Players open tracks in {{ provider.name }}</div>
        </button>
    </div>
</template>
