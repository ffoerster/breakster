<script setup>
import { useElementBounding, useMediaControls } from '@vueuse/core';
import { useDrag } from '@vueuse/gesture';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    src: {
        type: String,
        required: true,
    },
    autoplay: {
        type: Boolean,
        default: false,
    },
});

const audioRef = ref(null);
const progressBarEl = ref(null);
const autoplayBlocked = ref(false);
const isScrubbing = ref(false);

const { playing: isPlaying, currentTime, duration, ended } = useMediaControls(audioRef);

const isLoaded = computed(() => Number.isFinite(duration.value) && duration.value > 0);

const { left: progressLeft, width: progressWidth } = useElementBounding(progressBarEl);

const progress = computed(() => {
    if (!duration.value) {
        return 0;
    }
    return (currentTime.value / duration.value) * 100;
});

const formatTime = (seconds) => {
    if (!seconds || !Number.isFinite(seconds)) {
        return '0:00';
    }
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

const tryPlay = async () => {
    if (!audioRef.value) {
        return;
    }

    try {
        await audioRef.value.play();
        autoplayBlocked.value = false;
    } catch {
        autoplayBlocked.value = true;
    }
};

const togglePlay = async () => {
    if (!audioRef.value) {
        return;
    }

    if (isPlaying.value) {
        audioRef.value.pause();
        return;
    }

    await tryPlay();
};

const seek = (event) => {
    if (!audioRef.value || !duration.value) {
        return;
    }

    const width = progressWidth.value;
    if (!width) {
        return;
    }

    const clickX = event.clientX - progressLeft.value;
    const ratio = Math.min(1, Math.max(0, clickX / width));
    const seekTime = ratio * duration.value;
    audioRef.value.currentTime = seekTime;
};

const seekToClientX = (clientX) => {
    if (!audioRef.value || !duration.value) {
        return;
    }

    const width = progressWidth.value;
    if (!width) {
        return;
    }

    const x = clientX - progressLeft.value;
    const ratio = Math.min(1, Math.max(0, x / width));
    audioRef.value.currentTime = ratio * duration.value;
};

useDrag(
    ({ xy: [x], first, last, event }) => {
        event?.preventDefault?.();

        if (first) {
            isScrubbing.value = true;
        }

        seekToClientX(x);

        if (last) {
            isScrubbing.value = false;
        }
    },
    {
        target: progressBarEl,
        axis: 'x',
        filterTaps: true,
        eventOptions: { passive: false },
    },
);

onMounted(() => {
    if (audioRef.value && props.autoplay) {
        void tryPlay();
    }
});

watch(
    () => props.src,
    () => {
        autoplayBlocked.value = false;

        if (audioRef.value) {
            audioRef.value.pause();
            audioRef.value.currentTime = 0;
        }
    },
);

watch(ended, (hasEnded) => {
    if (!hasEnded || !audioRef.value) {
        return;
    }

    audioRef.value.currentTime = 0;
});

onBeforeUnmount(() => {
    if (audioRef.value) {
        audioRef.value.pause();
    }
});
</script>

<template>
    <div class="bg-white/5 backdrop-blur rounded-xl p-4 border border-white/10">
        <audio
            ref="audioRef"
            :src="src"
            preload="metadata"
        />

        <div class="flex items-center gap-4">
            <button
                type="button"
                class="btn-interactive w-12 h-12 rounded-full bg-accent-500 hover:bg-accent-600 flex items-center justify-center shrink-0"
                :disabled="!isLoaded"
                @click="togglePlay"
            >
                <svg
                    v-if="!isPlaying"
                    class="w-5 h-5 text-white ml-0.5"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"
                        clip-rule="evenodd"
                    />
                </svg>
                <svg
                    v-else
                    class="w-5 h-5 text-white"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path
                        fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>

            <div class="flex-1 space-y-1">
                <div
                    ref="progressBarEl"
                    class="relative h-2 rounded-full bg-white/10 cursor-pointer overflow-hidden"
                    @click="seek"
                >
                    <div
                        class="absolute inset-y-0 left-0 bg-accent-500 rounded-full"
                        :class="isScrubbing ? '' : 'transition-[width] duration-100'"
                        :style="{ width: `${progress}%` }"
                    />
                </div>
                <div v-if="autoplayBlocked" class="text-xs text-amber-400">
                    Tap play to start audio
                </div>
                <div class="flex justify-between text-xs text-white/60 font-mono-party">
                    <span>{{ formatTime(currentTime) }}</span>
                    <span>{{ formatTime(duration) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
