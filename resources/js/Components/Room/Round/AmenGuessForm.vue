<script setup>
import { ref } from "vue";

const props = defineProps({
    isSubmitting: {
        type: Boolean,
        required: true,
    },
    error: {
        type: String,
        default: "",
    },
    submitGuess: {
        type: Function,
        required: true,
    },
});

const artist = ref("");
const title = ref("");

const handleSubmit = async () => {
    if (!artist.value.trim() || !title.value.trim() || props.isSubmitting) {
        return;
    }

    await props.submitGuess(artist.value.trim(), title.value.trim());
};
</script>

<template>
    <div
        class="p-6 bg-linear-to-br from-amber-500/20 to-orange-600/20 border border-amber-500/50 rounded-xl"
    >
        <h3 class="text-lg font-bold text-amber-300 mb-4 text-center font-display">
            Alright. Name it.
        </h3>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
                <label
                    for="amen-artist"
                    class="block text-sm font-medium text-white/70 mb-1 font-display"
                >
                    Artist
                </label>
                <input
                    id="amen-artist"
                    v-model="artist"
                    type="text"
                    class="w-full px-4 py-3 bg-white/8 border border-white/10 rounded-lg text-white placeholder-white/35 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors"
                    placeholder="Artist name..."
                    :disabled="isSubmitting"
                    autocomplete="off"
                />
            </div>

            <div>
                <label
                    for="amen-title"
                    class="block text-sm font-medium text-white/70 mb-1 font-display"
                >
                    Track Title
                </label>
                <input
                    id="amen-title"
                    v-model="title"
                    type="text"
                    class="w-full px-4 py-3 bg-white/8 border border-white/10 rounded-lg text-white placeholder-white/35 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors"
                    placeholder="Track title..."
                    :disabled="isSubmitting"
                    autocomplete="off"
                />
            </div>

            <button
                type="submit"
                class="btn-interactive btn-warning w-full px-6 py-3 disabled:cursor-not-allowed rounded-lg font-bold transition-colors"
                :disabled="!artist.trim() || !title.trim() || isSubmitting"
            >
                <span v-if="isSubmitting" class="flex items-center justify-center gap-2">
                    <span
                        class="w-5 h-5 rounded-full border-2 border-white/30 border-t-white animate-spin"
                    />
                    <span>Submitting...</span>
                </span>
                <span v-else>Submit Guess</span>
            </button>

            <p v-if="error" class="text-sm text-red-400 text-center animate-shake">
                {{ error }}
            </p>
        </form>
    </div>
</template>
