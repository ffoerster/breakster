<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePreferredReducedMotion } from '@vueuse/core';

const props = defineProps({
    track: {
        type: Object,
        required: true,
    },
    delay: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['flipComplete']);

const prefersReducedMotion = usePreferredReducedMotion();
const isFlipped = ref(false);

const actualYear = computed(() => props.track?.year);

onMounted(() => {
    if (prefersReducedMotion.value === 'reduce') {
        isFlipped.value = true;
        emit('flipComplete');
        return;
    }

    setTimeout(() => {
        isFlipped.value = true;
        setTimeout(() => emit('flipComplete'), 600);
    }, props.delay);
});
</script>

<template>
    <div class="perspective-1000">
        <div
            class="flip-card relative preserve-3d"
            :class="{ 'rotate-y-180': isFlipped }"
        >
            <!-- Front: Mystery -->
            <div class="backface-hidden">
                <div class="p-8 bg-gradient-to-br from-accent-600/30 to-pink-600/30 border-2 border-accent-500/50 rounded-xl">
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                        <div class="w-32 h-32 rounded-lg bg-gray-700/50 flex items-center justify-center">
                            <span class="text-6xl font-black text-accent-300/50">?</span>
                        </div>
                        <div class="text-center sm:text-left">
                            <div class="text-[clamp(2.75rem,10vw,4rem)] font-black text-accent-300/50 leading-none mb-3">
                                ????
                            </div>
                            <div class="h-6 bg-gray-600/50 rounded w-40 mb-2" />
                            <div class="h-5 bg-gray-600/30 rounded w-32" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back: Revealed -->
            <div class="backface-hidden rotate-y-180 absolute inset-0">
                <div class="p-8 bg-gradient-to-br from-accent-600/20 to-purple-600/20 border-2 border-accent-500 rounded-xl">
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                        <img
                            v-if="track?.artwork_url"
                            :src="track.artwork_url"
                            alt=""
                            class="w-32 h-32 rounded-lg shadow-lg"
                        />
                        <div class="text-center sm:text-left">
                            <div class="text-[clamp(2.75rem,10vw,4rem)] font-black text-accent-400 leading-none mb-3">
                                {{ actualYear }}
                            </div>
                            <h2 class="text-2xl font-bold">{{ track?.title }}</h2>
                            <p class="text-lg text-gray-300">{{ track?.artist }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
