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
    isLocked: {
        type: Boolean,
        required: true,
    },
    hasPlaced: {
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
        <!-- Ready button (when not ready and not placed) -->
        <button
            v-if="!isReady && !hasPlaced"
            type="button"
            class="btn-interactive w-full py-3 px-4 rounded-lg font-medium transition-all duration-200"
            :class="
                isLocked
                    ? 'bg-gray-700 text-gray-500 cursor-not-allowed'
                    : 'bg-blue-600 hover:bg-blue-700 text-white'
            "
            :disabled="isLocked || isMarking"
            @click="markReady"
        >
            <span v-if="isMarking" class="flex items-center justify-center gap-2">
                <span class="w-4 h-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                <span>Marking...</span>
            </span>
            <span v-else-if="isLocked">Waiting...</span>
            <span v-else>I'm Ready</span>
        </button>

        <!-- Ready confirmation (when ready but not placed) -->
        <div
            v-else-if="isReady && !hasPlaced"
            class="flex items-center justify-center gap-2 py-3 px-4 bg-blue-500/20 border border-blue-500 rounded-lg"
        >
            <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                <path
                    fill-rule="evenodd"
                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                    clip-rule="evenodd"
                />
            </svg>
            <span class="font-medium text-blue-300">You're ready!</span>
        </div>

        <!-- Ready count -->
        <div class="text-center text-xs text-gray-400">
            {{ readyCount }} / {{ totalPlayers }} ready
        </div>

        <!-- Error -->
        <p v-if="error" class="text-sm text-red-400 text-center animate-shake">
            {{ error }}
        </p>
    </div>
</template>
