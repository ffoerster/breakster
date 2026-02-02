<script setup>
import GlassCard from '../../Ui/GlassCard.vue';
import BottomActionBar from '../../Ui/BottomActionBar.vue';
import AmenGuessForm from '../Round/AmenGuessForm.vue';
import AmenResult from '../Round/AmenResult.vue';
import AmenVotePanel from '../Round/AmenVotePanel.vue';

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
</script>

<template>
    <div class="space-y-6">
        <GlassCard class="p-8 text-center">
            <div class="text-sm text-gray-400 mb-2">Round {{ state.round.round_number }} - Revealed!</div>

            <div
                v-motion
                :initial="{ scale: 0.9, opacity: 0 }"
                :enter="{ scale: 1, opacity: 1, transition: { type: 'spring', stiffness: 200, damping: 20 } }"
                class="flex flex-col sm:flex-row items-center justify-center gap-6"
            >
                <img
                    v-if="state.round.track?.artwork_url"
                    :src="state.round.track.artwork_url"
                    alt=""
                    class="w-32 h-32 rounded-lg shadow-lg"
                />
                <div class="text-left">
                    <div
                        v-motion
                        :initial="{ y: 20, opacity: 0 }"
                        :enter="{ y: 0, opacity: 1, transition: { delay: 100, type: 'spring', stiffness: 300, damping: 25 } }"
                        class="text-[clamp(2.75rem,10vw,4rem)] font-black text-accent-400 leading-none mb-3"
                    >
                        {{ state.round.track?.year }}
                    </div>
                    <h2 class="text-2xl font-bold">{{ state.round.track?.title }}</h2>
                    <p class="text-lg text-gray-300">{{ state.round.track?.artist }}</p>
                </div>
            </div>
        </GlassCard>

        <div v-if="state.round?.amen?.claimed_by" class="space-y-6">
            <!-- AMEN Result (resolved) -->
            <AmenResult
                v-if="state.round.amen.resolved"
                :claimant-name="state.round.amen.claimant_name"
                :guess="state.round.amen.guess"
                :actual="state.round.amen.actual"
                :correct="state.round.amen.correct"
                :awarded-points="state.round.amen.awarded_points"
                :votes="state.round.amen.votes"
            />

            <!-- AMEN Voting (only after reveal) -->
            <template v-else-if="state.round.is_revealed && state.round.amen.voting_open">
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
                <TransitionGroup name="result-list" tag="div" class="space-y-3">
                    <div
                        v-for="(placement, index) in (state.round.placements ?? [])"
                        :key="placement.id"
                        :style="{ '--stagger-index': index }"
                        class="flex items-center justify-between p-4 rounded-lg border"
                        :class="placement.is_correct ? 'bg-green-500/20 border-green-500' : 'bg-red-500/20 border-red-500'"
                    >
                        <div>
                            <div class="font-medium flex items-center gap-2">
                                {{ placement.player.display_name }}
                                <span
                                    v-if="placement.is_auto_placed"
                                    class="px-2 py-0.5 text-xs font-medium rounded bg-gray-600 text-gray-300"
                                >
                                    Auto-placed
                                </span>
                            </div>
                            <div class="text-sm text-gray-400">
                                <span v-if="placement.inserted_after_track_id === null">Placed at beginning</span>
                                <span v-else>Placed after {{ placement.inserted_after_year ?? 'unknown' }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div
                                v-if="placement.is_correct"
                                class="text-green-400 font-bold animate-scale-pop"
                            >
                                +{{ placement.points_earned }} pts
                            </div>
                            <div v-else class="text-red-400">0 pts</div>
                        </div>
                    </div>
                </TransitionGroup>
            </GlassCard>

            <GlassCard class="p-8 lg:col-span-5">
                <h3 class="text-xl font-bold mb-4">Leaderboard</h3>
                <div class="space-y-2">
                    <div
                        v-for="(p, index) in leaderboard"
                        :key="p.id"
                        v-motion
                        :initial="{ x: -20, opacity: 0 }"
                        :enter="{ x: 0, opacity: 1, transition: { delay: 200 + index * 60, type: 'spring', stiffness: 300, damping: 25 } }"
                        class="flex items-center justify-between p-3 rounded-lg"
                        :class="p.is_me ? 'bg-accent-500/20' : 'bg-gray-700/30'"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center font-bold text-sm">
                                {{ p.rank }}
                            </div>
                            <span>{{ p.display_name }}</span>
                        </div>
                        <span class="font-bold text-accent-400 tabular-nums">{{ p.score }} pts</span>
                    </div>
                </div>
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
    </div>
</template>
