<script setup>
import { computed } from 'vue';

import GlassCard from '../../Ui/GlassCard.vue';

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
                        class="text-5xl font-black text-accent-400 leading-none mb-3"
                    >
                        {{ state.round.track?.year }}
                    </div>
                    <h2 class="text-2xl font-bold">{{ state.round.track?.title }}</h2>
                    <p class="text-lg text-gray-300">{{ state.round.track?.artist }}</p>
                </div>
            </div>
        </GlassCard>

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
                            <div class="font-medium">{{ placement.player.display_name }}</div>
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

        <div v-if="isHost" class="flex justify-center">
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
    </div>
</template>
