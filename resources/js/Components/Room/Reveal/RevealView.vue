<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePreferredReducedMotion } from '@vueuse/core';
import GlassCard from '../../Ui/GlassCard.vue';
import BottomActionBar from '../../Ui/BottomActionBar.vue';
import AmenGuessForm from '../Round/AmenGuessForm.vue';
import AmenVotePanel from '../Round/AmenVotePanel.vue';
import RevealTrackCard from './RevealTrackCard.vue';
import RevealResultItem from './RevealResultItem.vue';
import RevealLeaderboard from './RevealLeaderboard.vue';
import AmenRevealCard from './AmenRevealCard.vue';
import ScreenFlash from './ScreenFlash.vue';

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    isHost: {
        type: Boolean,
        required: true,
    },
    leaderboard: {
        type: Array,
        required: true,
    },
    previousLeaderboard: {
        type: Array,
        default: () => [],
    },
    nextRound: {
        type: Function,
        required: true,
    },
    openDetails: {
        type: Function,
        required: true,
    },
    submitAmenGuess: {
        type: Function,
        required: true,
    },
    isSubmittingAmenGuess: {
        type: Boolean,
        required: true,
    },
    amenGuessError: {
        type: String,
        default: '',
    },
    submitAmenVote: {
        type: Function,
        required: true,
    },
    isSubmittingAmenVote: {
        type: Boolean,
        required: true,
    },
    amenVoteError: {
        type: String,
        default: '',
    },
});

const prefersReducedMotion = usePreferredReducedMotion();

const showResults = ref(false);
const showAmen = ref(false);
const showLeaderboard = ref(false);
const screenFlashActive = ref(false);

let confettiLib = null;

const actualYear = computed(() => {
    const year = props.state.round?.track?.year;
    return year ? parseInt(year, 10) : 0;
});

const hasAmenResolved = computed(() => {
    return props.state.round?.amen?.claimed_by && props.state.round?.amen?.resolved;
});

const getConfetti = async () => {
    if (confettiLib) {
        return confettiLib;
    }

    const mod = await import('canvas-confetti');
    confettiLib = mod.default;
    return confettiLib;
};

const triggerConfetti = async () => {
    if (prefersReducedMotion.value === 'reduce') {
        return;
    }

    const confetti = await getConfetti();
    confetti({
        particleCount: 40,
        spread: 60,
        origin: { x: 0.5, y: 0.6 },
        colors: ['#00d4ff', '#22c55e', '#ffd93d'],
    });
};

const triggerScreenFlash = () => {
    screenFlashActive.value = true;
    setTimeout(() => {
        screenFlashActive.value = false;
    }, 50);
};

const onTrackFlipComplete = () => {
    showResults.value = true;
};

const onResultAnimationComplete = () => {
    // Results are animating
};

onMounted(() => {
    if (prefersReducedMotion.value === 'reduce') {
        showResults.value = true;
        showAmen.value = true;
        showLeaderboard.value = true;
        return;
    }

    setTimeout(() => {
        showAmen.value = true;
    }, 1400);

    setTimeout(() => {
        showLeaderboard.value = true;
    }, 1800);
});
</script>

<template>
    <div class="space-y-6">
        <GlassCard class="p-8 text-center">
            <div class="text-sm text-gray-400 mb-4">Round {{ state.round.round_number }} - Revealed!</div>

            <RevealTrackCard
                v-if="state.round.track"
                :track="state.round.track"
                :delay="0"
                @flip-complete="onTrackFlipComplete"
            />
        </GlassCard>

        <div v-if="state.round?.amen?.claimed_by" class="space-y-6">
            <!-- AMEN Result (resolved) - Enhanced -->
            <Transition name="view-fade">
                <AmenRevealCard
                    v-if="hasAmenResolved && showAmen"
                    :claimant-name="state.round.amen.claimant_name"
                    :guess="state.round.amen.guess"
                    :actual="state.round.amen.actual"
                    :correct="state.round.amen.correct"
                    :awarded-points="state.round.amen.awarded_points"
                    :votes="state.round.amen.votes"
                    :delay="0"
                />
            </Transition>

            <!-- AMEN Voting (only after reveal, not resolved yet) -->
            <template v-if="!state.round.amen.resolved && state.round.is_revealed && state.round.amen.voting_open">
                <AmenGuessForm
                    v-if="state.round.amen.is_claimant && !state.round.amen.has_guess"
                    :is-submitting="isSubmittingAmenGuess"
                    :error="amenGuessError"
                    :submit-guess="submitAmenGuess"
                />

                <AmenVotePanel
                    v-else-if="!state.round.amen.is_claimant && state.round.amen.has_guess"
                    :claimant-name="state.round.amen.claimant_name"
                    :guess="state.round.amen.guess"
                    :actual="state.round.track ? { artist: state.round.track.artist, title: state.round.track.title } : null"
                    :can-vote="state.round.amen.can_vote"
                    :my-vote="state.round.amen.my_vote"
                    :votes="state.round.amen.votes"
                    :is-submitting="isSubmittingAmenVote"
                    :error="amenVoteError"
                    :submit-vote="submitAmenVote"
                />

                <GlassCard
                    v-else-if="state.round.amen.is_claimant && state.round.amen.has_guess"
                    class="p-6 bg-gradient-to-br from-amber-500/20 to-orange-600/20 border border-amber-500/50"
                >
                    <h3 class="text-lg font-bold text-amber-300 mb-2 text-center">
                        Waiting for votes...
                    </h3>

                    <div v-if="state.round.amen.guess && state.round.track" class="grid grid-cols-2 gap-4 text-sm">
                        <div class="text-center">
                            <p class="text-gray-400 text-xs uppercase tracking-wider mb-1">Guessed</p>
                            <p class="font-bold text-white">{{ state.round.amen.guess.artist }}</p>
                            <p class="text-gray-300">{{ state.round.amen.guess.title }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-400 text-xs uppercase tracking-wider mb-1">Actual</p>
                            <p class="font-bold text-white">{{ state.round.track.artist }}</p>
                            <p class="text-gray-300">{{ state.round.track.title }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-center gap-6 text-sm">
                        <span class="text-green-400">
                            <span class="font-bold">{{ state.round.amen.votes.yes }}</span> Ja
                        </span>
                        <span class="text-red-400">
                            <span class="font-bold">{{ state.round.amen.votes.no }}</span> Nein
                        </span>
                    </div>
                </GlassCard>

                <GlassCard v-else class="p-6 bg-gray-800/30 border border-white/10">
                    <p class="text-center text-gray-300">Waiting for the AMEN guess…</p>
                </GlassCard>
            </template>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <GlassCard class="p-8 lg:col-span-7">
                <h3 class="text-xl font-bold mb-4">Results</h3>
                <Transition name="view-fade">
                    <div v-if="showResults" class="space-y-3">
                        <RevealResultItem
                            v-for="(placement, index) in (state.round.placements ?? [])"
                            :key="placement.id"
                            :placement="placement"
                            :actual-year="actualYear"
                            :index="index"
                            @trigger-confetti="triggerConfetti"
                            @trigger-screen-flash="triggerScreenFlash"
                            @animation-complete="onResultAnimationComplete"
                        />
                    </div>
                </Transition>
            </GlassCard>

            <GlassCard class="p-8 lg:col-span-5">
                <h3 class="text-xl font-bold mb-4">Leaderboard</h3>
                <Transition name="view-fade">
                    <RevealLeaderboard
                        v-if="showLeaderboard"
                        :leaderboard="leaderboard"
                        :previous-leaderboard="previousLeaderboard"
                        :delay="0"
                    />
                </Transition>
            </GlassCard>
        </div>

        <div v-if="isHost" class="hidden lg:flex justify-center">
            <div class="flex flex-col sm:flex-row gap-3">
                <button
                    type="button"
                    class="btn-interactive px-8 py-4 bg-green-600 hover:bg-green-700 rounded-lg font-bold text-lg"
                    @click="nextRound"
                >
                    Next Round
                </button>
            </div>
        </div>
        <div v-else class="text-center text-gray-400">Waiting for host to start the next round…</div>

        <BottomActionBar class="lg:hidden" max-width-class="max-w-6xl">
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="btn-interactive min-h-[44px] px-4 py-3 rounded-xl font-bold bg-gray-800/70 text-white"
                    @click="openDetails"
                >
                    Details
                </button>

                <button
                    v-if="isHost"
                    type="button"
                    class="btn-interactive min-h-[44px] px-4 py-3 bg-green-600 text-white font-bold rounded-xl"
                    @click="nextRound"
                >
                    Next
                </button>

                <div
                    v-else
                    class="min-h-[44px] px-4 py-3 bg-gray-900/40 text-gray-300 font-bold rounded-xl text-center flex items-center justify-center"
                >
                    Waiting…
                </div>
            </div>
        </BottomActionBar>

        <ScreenFlash :active="screenFlashActive" />
    </div>
</template>
