<script setup>
defineProps({
    isReady: {
        type: Boolean,
        required: true,
    },
    isMarking: {
        type: Boolean,
        required: true,
    },
    markReady: {
        type: Function,
        required: true,
    },
    readyCount: {
        type: Number,
        default: 0,
    },
    totalPlayers: {
        type: Number,
        default: 0,
    },
    error: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="space-y-2">
        <!-- Ready button (when not ready) -->
        <button
            v-if="!isReady"
            type="button"
            class="btn-interactive btn-primary w-full py-3 px-4 rounded-lg font-medium transition-all duration-200 text-white"
            :disabled="isMarking"
            @click="markReady"
        >
            <span v-if="isMarking" class="flex items-center justify-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                <span>Marking...</span>
            </span>
            <span v-else>I'm Ready</span>
        </button>

        <!-- Ready confirmation (when ready) -->
        <div
            v-else
            class="flex items-center justify-center gap-2 py-3 px-4 bg-accent-500/15 border border-accent-500/40 rounded-lg"
        >
            <svg class="w-5 h-5 text-accent-200" fill="currentColor" viewBox="0 0 20 20">
                <path
                    fill-rule="evenodd"
                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                    clip-rule="evenodd"
                />
            </svg>
            <span class="font-medium text-accent-200">You're ready!</span>
        </div>

        <!-- Ready count -->
        <div class="text-center text-xs text-white/60 font-mono-party">
            {{ readyCount }} / {{ totalPlayers }} ready
        </div>

        <!-- Error -->
        <p v-if="error" class="text-sm text-red-400 text-center animate-shake">
            {{ error }}
        </p>
    </div>
</template>
