<script setup>
const props = defineProps({
    insertAfterTrackId: {
        type: [Number, null],
        default: null,
    },
    isActive: {
        type: Boolean,
        default: false,
    },
    hasPlaced: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    isPlacing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["place", "undo"]);

const handleClick = () => {
    if (props.disabled) {
        return;
    }

    if (props.isActive) {
        return;
    }

    emit("place", props.insertAfterTrackId);
};

const handleUndo = () => {
    if (props.disabled) {
        return;
    }

    emit("undo");
};
</script>

<template>
    <!-- Active: mystery card is placed here -->
    <div v-if="isActive" class="relative">
        <div
            class="flex items-center gap-3 p-4 rounded-lg bg-accent-500/10 border-2 border-accent-500/40"
        >
            <div
                class="w-14 h-14 rounded bg-accent-900/50 flex items-center justify-center shrink-0"
            >
                <svg class="w-7 h-7 text-accent-200" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        d="M10 2a5 5 0 00-5 5v2a2 2 0 00-2 2v5a2 2 0 002 2h10a2 2 0 002-2v-5a2 2 0 00-2-2H7V7a3 3 0 015.905-.75 1 1 0 001.937-.5A5.002 5.002 0 0010 2z"
                    />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-2xl font-black text-accent-200 leading-none mb-1">???</div>
                <div class="font-medium text-sm text-white">Your guess</div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <div
                    v-if="isPlacing"
                    class="w-6 h-6 rounded-full border-2 border-white/25 border-t-white/80 animate-spin"
                />
                <div
                    v-else
                    class="w-7 h-7 rounded-full bg-green-500/15 border border-green-500/50 flex items-center justify-center"
                >
                    <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>

                <button
                    v-if="!disabled"
                    type="button"
                    class="btn-interactive w-8 h-8 rounded-full bg-white/10 hover:bg-red-500/20 border border-white/20 hover:border-red-500/40 flex items-center justify-center transition-colors"
                    title="Undo placement"
                    @click.stop="handleUndo"
                >
                    <svg
                        class="w-4 h-4 text-white/70"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Idle: tappable drop zone -->
    <button
        v-else
        type="button"
        class="w-full rounded-lg border-2 border-dashed transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400/50"
        :class="[
            disabled
                ? 'border-white/10 bg-white/3 opacity-40 cursor-not-allowed'
                : hasPlaced
                  ? 'drop-zone-idle border-accent-500/20 bg-transparent min-h-[32px] hover:min-h-11'
                  : 'drop-zone-waiting border-accent-500/30 bg-accent-500/5 min-h-11',
        ]"
        :disabled="disabled"
        @click="handleClick"
    >
        <div
            class="flex items-center justify-center gap-2 py-1"
            :class="disabled ? 'text-white/30' : hasPlaced ? 'text-white/40' : 'text-accent-300/70'"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6v12m6-6H6"
                />
            </svg>
            <span class="text-xs font-medium font-display uppercase tracking-wider">
                {{ hasPlaced ? "Move here" : "Place here" }}
            </span>
        </div>
    </button>
</template>
