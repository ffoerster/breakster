<script setup>
defineProps({
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
</script>

<template>
    <div
        class="p-6 rounded-xl border-2"
        :class="correct ? 'bg-green-500/20 border-green-500' : 'bg-red-500/20 border-red-500'"
    >
        <h3 class="text-xl font-bold mb-3 text-center" :class="correct ? 'text-green-400' : 'text-red-400'">
            {{ correct ? 'AMEN confirmed.' : 'False AMEN.' }}
            <span class="ml-2">
                {{ awardedPoints > 0 ? `+${awardedPoints}` : awardedPoints }}
            </span>
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

        <div class="mt-4 flex justify-center gap-6 text-sm border-t border-gray-700/50 pt-3">
            <span class="text-green-400">
                <span class="font-bold">{{ votes.yes }}</span> voted Ja
            </span>
            <span class="text-red-400">
                <span class="font-bold">{{ votes.no }}</span> voted Nein
            </span>
        </div>
    </div>
</template>
