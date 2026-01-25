<script setup>
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
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const isLoaded = ref(false);

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

const togglePlay = () => {
    if (!audioRef.value) {
        return;
    }

    if (isPlaying.value) {
        audioRef.value.pause();
    } else {
        audioRef.value.play();
    }
};

const seek = (event) => {
    if (!audioRef.value || !duration.value) {
        return;
    }

    const rect = event.currentTarget.getBoundingClientRect();
    const clickX = event.clientX - rect.left;
    const width = rect.width;
    const seekTime = (clickX / width) * duration.value;
    audioRef.value.currentTime = seekTime;
};

const handleTimeUpdate = () => {
    if (audioRef.value) {
        currentTime.value = audioRef.value.currentTime;
    }
};

const handleLoadedMetadata = () => {
    if (audioRef.value) {
        duration.value = audioRef.value.duration;
        isLoaded.value = true;
    }
};

const handlePlay = () => {
    isPlaying.value = true;
};

const handlePause = () => {
    isPlaying.value = false;
};

const handleEnded = () => {
    isPlaying.value = false;
    currentTime.value = 0;
};

onMounted(() => {
    if (audioRef.value && props.autoplay) {
        audioRef.value.play().catch(() => {
            // Autoplay blocked, user needs to interact
        });
    }
});

watch(
    () => props.src,
    () => {
        isPlaying.value = false;
        currentTime.value = 0;
        duration.value = 0;
        isLoaded.value = false;
    },
);

onBeforeUnmount(() => {
    if (audioRef.value) {
        audioRef.value.pause();
    }
});
</script>

<template>
    <div class="bg-gray-800/60 backdrop-blur rounded-xl p-4 border border-gray-700/50">
        <audio
            ref="audioRef"
            :src="src"
            preload="metadata"
            @timeupdate="handleTimeUpdate"
            @loadedmetadata="handleLoadedMetadata"
            @play="handlePlay"
            @pause="handlePause"
            @ended="handleEnded"
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
                    class="relative h-2 rounded-full bg-gray-700 cursor-pointer overflow-hidden"
                    @click="seek"
                >
                    <div
                        class="absolute inset-y-0 left-0 bg-accent-500 rounded-full transition-[width] duration-100"
                        :style="{ width: `${progress}%` }"
                    />
                </div>
                <div class="flex justify-between text-xs text-gray-400 tabular-nums">
                    <span>{{ formatTime(currentTime) }}</span>
                    <span>{{ formatTime(duration) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
