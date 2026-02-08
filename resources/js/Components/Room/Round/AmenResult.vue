<script setup>
import { computed } from "vue";

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
});

const isNeutral = computed(() => props.correct === null);
const isCorrect = computed(() => props.correct === true);
const isWrong = computed(() => props.correct === false);
</script>

<template>
    <div
        class="p-6 rounded-xl border-2"
        :class="isCorrect ? 'bg-green-500/20 border-green-500' : isWrong ? 'bg-red-500/20 border-red-500' : 'bg-white/10 border-white/20'"
    >
        <h3
            class="text-xl font-bold mb-3 text-center font-display"
            :class="isCorrect ? 'text-green-300' : isWrong ? 'text-red-300' : 'text-white/85'"
        >
            {{ isCorrect ? 'AMEN confirmed.' : isWrong ? 'False AMEN.' : 'No verdict.' }}
            <span class="ml-2">
                {{ awardedPoints > 0 ? `+${awardedPoints}` : awardedPoints }}
            </span>
        </h3>

        <p class="text-sm text-white/60 text-center mb-4 font-mono-party">
            {{ claimantName }}'s AMEN
        </p>

        <div v-if="guess && actual" class="space-y-3">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="text-center">
                    <p class="text-white/60 text-xs uppercase tracking-wider mb-1 font-display">Guessed</p>
                    <p class="font-bold text-white">{{ guess.artist }}</p>
                    <p class="text-white/80">{{ guess.title }}</p>
                </div>
                <div class="text-center">
                    <p class="text-white/60 text-xs uppercase tracking-wider mb-1 font-display">Actual</p>
                    <p class="font-bold text-white">{{ actual.artist }}</p>
                    <p class="text-white/80">{{ actual.title }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-center gap-6 text-sm border-t border-white/10 pt-3">
            <span class="text-green-400">
                <span class="font-bold">{{ votes.yes }}</span> voted Ja
            </span>
            <span class="text-red-400">
                <span class="font-bold">{{ votes.no }}</span> voted Nein
            </span>
        </div>
    </div>
</template>
