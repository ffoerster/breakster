<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { usePreferredReducedMotion } from '@vueuse/core';

const props = defineProps({
    claimantName: {
        type: String,
        required: true,
    },
    guess: {
        type: Object,
        default: null,
    },
    actual: {
        type: Object,
        default: null,
    },
    correct: {
        type: Boolean,
        default: null,
    },
    awardedPoints: {
        type: Number,
        default: null,
    },
    votes: {
        type: Object,
        required: true,
    },
    delay: {
        type: Number,
        default: 0,
    },
});

const prefersReducedMotion = usePreferredReducedMotion();
const cardRef = ref(null);
const showResult = ref(false);
let confettiLib = null;

const gameSounds = inject('gameSounds', null);

const loadConfetti = async () => {
    if (confettiLib) {
        return confettiLib;
    }

    const mod = await import('canvas-confetti');
    confettiLib = mod.default;
    return confettiLib;
};

const fireGoldConfetti = async () => {
    if (prefersReducedMotion.value === 'reduce') {
        return;
    }

    const confetti = await loadConfetti();
    const rect = cardRef.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    const x = (rect.left + rect.width / 2) / window.innerWidth;
    const y = (rect.top + rect.height / 2) / window.innerHeight;

    confetti({
        particleCount: 80,
        spread: 100,
        origin: { x, y },
        colors: ['#FFD700', '#FFA500', '#FFEC8B', '#DAA520'],
        scalar: 1.2,
    });
};

onMounted(() => {
    setTimeout(() => {
        showResult.value = true;

        if (props.correct) {
            fireGoldConfetti();
            gameSounds?.playCorrect();
        } else {
            gameSounds?.playWrong();
        }
    }, props.delay);
});
</script>

<template>
    <div
        ref="cardRef"
        class="relative overflow-hidden rounded-xl border-2 transition-all duration-500"
        :class="[
            showResult && correct
                ? 'bg-gradient-to-br from-amber-500/30 to-yellow-600/30 border-yellow-500 animate-pulse-glow-gold'
                : showResult && !correct
                    ? 'bg-red-500/20 border-red-500'
                    : 'bg-gray-800/50 border-gray-600',
        ]"
    >
        <!-- Spotlight effect overlay for success -->
        <div
            v-if="showResult && correct"
            class="absolute inset-0 bg-gradient-radial from-yellow-400/20 via-transparent to-transparent pointer-events-none"
        />

        <!-- Wilting overlay for failure -->
        <div
            v-if="showResult && !correct"
            class="absolute inset-0 bg-gradient-to-t from-gray-900/40 to-transparent pointer-events-none"
        />

        <div class="relative p-6">
            <h3
                class="text-xl font-bold mb-3 text-center transition-colors duration-300"
                :class="showResult ? (correct ? 'text-yellow-400' : 'text-red-400') : 'text-gray-300'"
            >
                <template v-if="showResult">
                    {{ correct ? 'AMEN confirmed!' : 'False AMEN.' }}
                </template>
                <template v-else>
                    Resolving AMEN...
                </template>
            </h3>

            <p class="text-sm text-gray-400 text-center mb-4">
                {{ claimantName }}'s AMEN
            </p>

            <div v-if="guess && actual" class="space-y-3">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="text-center">
                        <p class="text-gray-400 text-xs uppercase tracking-wider mb-1">Guessed</p>
                        <p class="font-bold text-white">{{ guess.artist }}</p>
                        <p class="text-gray-300">{{ guess.title }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-gray-400 text-xs uppercase tracking-wider mb-1">Actual</p>
                        <p class="font-bold text-white">{{ actual.artist }}</p>
                        <p class="text-gray-300">{{ actual.title }}</p>
                    </div>
                </div>
            </div>

            <!-- Points display -->
            <div
                v-if="showResult"
                class="mt-4 text-center text-2xl font-black"
                :class="correct ? 'text-yellow-400 animate-scale-pop' : 'text-red-400'"
            >
                {{ awardedPoints > 0 ? `+${awardedPoints}` : awardedPoints }} pts
            </div>

            <!-- Vote counts -->
            <div class="mt-4 flex justify-center gap-6 text-sm border-t border-gray-700/50 pt-3">
                <span class="text-green-400">
                    <span class="font-bold">{{ votes.yes }}</span> voted Ja
                </span>
                <span class="text-red-400">
                    <span class="font-bold">{{ votes.no }}</span> voted Nein
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-gradient-radial {
    background: radial-gradient(circle at center, var(--tw-gradient-from), var(--tw-gradient-via), var(--tw-gradient-to));
}
</style>
