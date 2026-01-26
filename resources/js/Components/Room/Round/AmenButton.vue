<script setup>
import { ref } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'full',
        validator: (value) => ['full', 'compact'].includes(value),
    },
    canClaim: {
        type: Boolean,
        required: true,
    },
    isClaiming: {
        type: Boolean,
        required: true,
    },
    error: {
        type: String,
        default: '',
    },
    claimAmen: {
        type: Function,
        required: true,
    },
});

const amenAudio = ref(null);

const buttonClass = () => {
    if (props.variant === 'compact') {
        return 'btn-interactive w-full min-h-[44px] px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-600 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed rounded-xl font-black text-xl tracking-wider shadow-lg shadow-orange-500/30 disabled:shadow-none transition-all duration-200';
    }

    return 'btn-interactive relative px-8 py-4 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed rounded-xl font-black text-2xl tracking-wider shadow-lg shadow-orange-500/30 disabled:shadow-none transition-all duration-200';
};

const handleClick = async () => {
    if (!props.canClaim || props.isClaiming) {
        return;
    }

    // Play amen sound immediately (optimistic)
    if (amenAudio.value) {
        amenAudio.value.currentTime = 0;
        amenAudio.value.play().catch(() => {});
    }

    await props.claimAmen();
};
</script>

<template>
    <div class="text-center">
        <audio ref="amenAudio" src="/audio/amen.mp3" preload="auto" />

        <button
            type="button"
            :class="buttonClass()"
            :disabled="!canClaim || isClaiming"
            :title="canClaim ? 'Nur drücken, wenn du\'s wirklich weißt.' : ''"
            @click="handleClick"
        >
            <span v-if="isClaiming" class="flex items-center gap-2">
                <span class="w-5 h-5 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                <span>AMEN</span>
            </span>
            <span v-else>AMEN</span>
        </button>

        <p v-if="variant === 'full' && canClaim" class="mt-2 text-xs text-gray-400">
            Nur drücken, wenn du's wirklich weißt.
        </p>

        <p v-if="error" class="mt-2 text-sm text-red-400 animate-shake">
            {{ error }}
        </p>
    </div>
</template>
