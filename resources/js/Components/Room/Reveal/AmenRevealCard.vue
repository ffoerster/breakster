<script setup>
import { usePreferredReducedMotion } from "@vueuse/core";
import { computed, inject, onMounted, ref } from "vue";
import { useHaptics } from "../../../Composables/useHaptics";

const haptics = useHaptics();

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

const isNeutral = computed(() => props.correct === null);
const isCorrect = computed(() => props.correct === true);
const isWrong = computed(() => props.correct === false);

const gameSounds = inject("gameSounds", null);

const loadConfetti = async () => {
    if (confettiLib) {
        return confettiLib;
    }

    const mod = await import("canvas-confetti");
    confettiLib = mod.default;
    return confettiLib;
};

const fireGoldConfetti = async () => {
    if (prefersReducedMotion.value === "reduce") {
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
        colors: ["#FFD700", "#FFA500", "#FFEC8B", "#DAA520"],
        scalar: 1.2,
    });
};

onMounted(() => {
    setTimeout(() => {
        showResult.value = true;

        if (isCorrect.value) {
            fireGoldConfetti();
            gameSounds?.playCorrect();
            haptics.heavy();
        } else if (isWrong.value) {
            gameSounds?.playWrong();
            haptics.error();
        }
    }, props.delay);
});
</script>

<template>
    <div
        ref="cardRef"
        class="relative overflow-hidden rounded-xl border-2 transition-all duration-500"
        :class="[
            showResult && isCorrect
                ? 'bg-linear-to-br from-accent-500/30 to-accent-600/30 border-accent-500 animate-pulse-glow'
                : showResult && isWrong
                  ? 'bg-red-500/20 border-red-500'
                  : showResult && isNeutral
                    ? 'bg-white/10 border-white/20'
                    : 'bg-white/5 border-accent-500/15',
        ]"
    >
        <!-- Spotlight effect overlay for success -->
        <div
            v-if="showResult && isCorrect"
            class="absolute inset-0 bg-gradient-radial from-accent-400/20 via-transparent to-transparent pointer-events-none"
        />

        <!-- Wilting overlay for failure -->
        <div
            v-if="showResult && isWrong"
            class="absolute inset-0 bg-linear-to-t from-gray-900/40 to-transparent pointer-events-none"
        />

        <div class="relative p-6">
            <h3
                class="text-xl font-bold mb-3 text-center transition-colors duration-300 font-display"
                :class="
                    showResult
                        ? isCorrect
                            ? 'text-accent-200'
                            : isWrong
                              ? 'text-red-300'
                              : 'text-white/85'
                        : 'text-white/80'
                "
            >
                <template v-if="showResult">
                    {{ isCorrect ? "AMEN confirmed!" : isWrong ? "False AMEN." : "No verdict." }}
                </template>
                <template v-else> Resolving AMEN... </template>
            </h3>

            <p class="text-sm text-white/60 text-center mb-4 font-mono-party">
                {{ claimantName }}'s AMEN
            </p>

            <div v-if="guess && actual" class="space-y-3">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="text-center">
                        <p class="text-white/60 text-xs uppercase tracking-wider mb-1 font-display">
                            Guessed
                        </p>
                        <p class="font-bold text-white">{{ guess.artist }}</p>
                        <p class="text-white/80">{{ guess.title }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-white/60 text-xs uppercase tracking-wider mb-1 font-display">
                            Actual
                        </p>
                        <p class="font-bold text-white">{{ actual.artist }}</p>
                        <p class="text-white/80">{{ actual.title }}</p>
                    </div>
                </div>
            </div>

            <!-- Points display -->
            <div
                v-if="showResult"
                class="mt-4 text-center text-2xl font-black font-mono-party"
                :class="
                    isCorrect
                        ? 'text-accent-200 animate-scale-pop'
                        : isWrong
                          ? 'text-red-300'
                          : 'text-white/80'
                "
            >
                {{ awardedPoints > 0 ? `+${awardedPoints}` : awardedPoints }} pts
            </div>

            <!-- Vote counts -->
            <div class="mt-4 flex justify-center gap-6 text-sm border-t border-white/10 pt-3">
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
    background: radial-gradient(
        circle at center,
        var(--tw-gradient-from),
        var(--tw-gradient-via),
        var(--tw-gradient-to)
    );
}
</style>
