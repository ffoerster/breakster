<script setup>
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
    canVote: {
        type: Boolean,
        required: true,
    },
    myVote: {
        type: [Boolean, null],
        default: null,
    },
    votes: {
        type: Object,
        required: true,
    },
    isSubmitting: {
        type: Boolean,
        required: true,
    },
    error: {
        type: String,
        default: '',
    },
    submitVote: {
        type: Function,
        required: true,
    },
});

const handleVote = async (vote) => {
    if (!props.canVote || props.isSubmitting) {
        return;
    }

    await props.submitVote(vote);
};
</script>

<template>
    <div class="p-6 bg-gradient-to-br from-amber-500/20 to-orange-600/20 border border-amber-500/50 rounded-xl">
        <h3 class="text-lg font-bold text-amber-300 mb-2 text-center font-display">
            {{ claimantName }} hat AMEN gerufen!
        </h3>

        <p class="text-sm text-white/60 mb-4 text-center">
            Hat {{ claimantName }} recht?
        </p>

        <div v-if="guess && actual" class="mb-4">
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

        <div v-if="canVote" class="flex gap-3">
            <button
                type="button"
                class="btn-interactive btn-success flex-1 px-4 py-3 disabled:cursor-not-allowed rounded-lg font-bold transition-colors"
                :disabled="isSubmitting"
                @click="handleVote(true)"
            >
                <span v-if="isSubmitting" class="w-5 h-5 mx-auto rounded-full border-2 border-white/30 border-t-white animate-spin" />
                <span v-else>Ja, safe.</span>
            </button>

            <button
                type="button"
                class="btn-interactive btn-danger flex-1 px-4 py-3 disabled:cursor-not-allowed rounded-lg font-bold transition-colors"
                :disabled="isSubmitting"
                @click="handleVote(false)"
            >
                <span v-if="isSubmitting" class="w-5 h-5 mx-auto rounded-full border-2 border-white/30 border-t-white animate-spin" />
                <span v-else>Nope.</span>
            </button>
        </div>

        <div v-else-if="myVote !== null" class="text-center">
            <p class="text-sm text-white/70">
                Du hast abgestimmt:
                <span :class="myVote ? 'text-green-400' : 'text-red-400'" class="font-bold">
                    {{ myVote ? 'Ja, safe.' : 'Nope.' }}
                </span>
            </p>
        </div>

        <div class="mt-4 flex justify-center gap-6 text-sm">
            <span class="text-green-400">
                <span class="font-bold">{{ votes.yes }}</span> Ja
            </span>
            <span class="text-red-400">
                <span class="font-bold">{{ votes.no }}</span> Nein
            </span>
        </div>

        <p v-if="error" class="mt-3 text-sm text-red-400 text-center animate-shake">
            {{ error }}
        </p>
    </div>
</template>
