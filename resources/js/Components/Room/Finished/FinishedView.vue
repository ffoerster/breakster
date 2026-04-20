<script setup>
import { useTimeoutFn } from "@vueuse/core";
import { computed, onMounted } from "vue";
import { Link } from "@inertiajs/vue3";

import GlassCard from "../../Ui/GlassCard.vue";
import AwardCard from "./AwardCard.vue";
import AmenStatsPanel from "./AmenStatsPanel.vue";

const props = defineProps({
    leaderboard: {
        type: Array,
        required: true,
    },
    summary: {
        type: Object,
        default: null,
    },
    isLoadingSummary: {
        type: Boolean,
        default: false,
    },
});

const mostAccurate = computed(() => props.summary?.awards?.most_accurate);
const biggestMiss = computed(() => props.summary?.awards?.biggest_miss);
const amenStats = computed(() => props.summary?.amen_stats);
const gameStats = computed(() => props.summary?.game_stats);

const formatYearDelta = (delta) => {
    if (delta === 0) {
        return "Perfect!";
    }
    return `${delta} year${delta !== 1 ? "s" : ""} off`;
};

let confettiLib = null;

const getConfetti = async () => {
    if (confettiLib) {
        return confettiLib;
    }

    const mod = await import("canvas-confetti");
    confettiLib = mod.default;

    return confettiLib;
};

const fireSecondConfettiBurst = async () => {
    const confetti = await getConfetti();

    confetti({
        particleCount: 50,
        angle: 60,
        spread: 55,
        origin: { x: 0 },
        colors: ["#00d4ff", "#ff6b6b", "#ffd93d"],
    });
    confetti({
        particleCount: 50,
        angle: 120,
        spread: 55,
        origin: { x: 1 },
        colors: ["#00d4ff", "#ff6b6b", "#ffd93d"],
    });
};

const { start: startSecondConfettiBurst } = useTimeoutFn(
    () => {
        void fireSecondConfettiBurst();
    },
    250,
    { immediate: false },
);

onMounted(async () => {
    const isWinner = props.leaderboard[0]?.is_me;

    if (isWinner) {
        const confetti = await getConfetti();

        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 },
            colors: ["#00d4ff", "#ff6b6b", "#ffd93d", "#6bcb77"],
        });

        startSecondConfettiBurst();
    }
});
</script>

<template>
    <div class="space-y-6">
        <GlassCard class="p-8 text-center">
            <h1
                v-motion
                :initial="{ scale: 0.8, opacity: 0 }"
                :enter="{
                    scale: 1,
                    opacity: 1,
                    transition: { type: 'spring', stiffness: 200, damping: 15 },
                }"
                class="text-4xl font-bold mb-6 font-display"
            >
                Game Over!
            </h1>

            <div
                v-if="leaderboard[0]"
                v-motion
                :initial="{ scale: 0.5, opacity: 0 }"
                :enter="{
                    scale: 1,
                    opacity: 1,
                    transition: { delay: 200, type: 'spring', stiffness: 180, damping: 12 },
                }"
                class="inline-block p-6 bg-yellow-500/20 rounded-2xl border border-yellow-500 mb-4 animate-pulse-glow"
            >
                <div class="text-yellow-300 text-sm mb-2 font-display">Winner</div>
                <div class="text-3xl font-bold">{{ leaderboard[0].display_name }}</div>
                <div class="text-2xl text-yellow-300 font-mono-party">
                    {{ leaderboard[0].score }} points
                </div>
            </div>
        </GlassCard>

        <GlassCard class="p-8">
            <h2 class="text-xl font-bold mb-4 text-center">Final Standings</h2>
            <div class="max-w-md mx-auto space-y-2">
                <div
                    v-for="(p, index) in leaderboard"
                    :key="p.id"
                    v-motion
                    :initial="{ x: -30, opacity: 0 }"
                    :enter="{
                        x: 0,
                        opacity: 1,
                        transition: {
                            delay: 400 + index * 80,
                            type: 'spring',
                            stiffness: 300,
                            damping: 25,
                        },
                    }"
                    class="flex items-center justify-between p-4 rounded-lg"
                    :class="
                        p.rank === 1
                            ? 'bg-yellow-500/20 border border-yellow-500'
                            : p.is_me
                              ? 'bg-accent-500/20'
                              : 'bg-white/5 border border-accent-500/15'
                    "
                >
                    <div class="flex items-center gap-3">
                        <div
                            v-motion
                            :initial="{ scale: 0 }"
                            :enter="{
                                scale: 1,
                                transition: {
                                    delay: 500 + index * 80,
                                    type: 'spring',
                                    stiffness: 400,
                                    damping: 15,
                                },
                            }"
                            class="w-10 h-10 rounded-full flex items-center justify-center font-bold"
                            :class="p.rank === 1 ? 'bg-yellow-500 text-black' : 'bg-white/10'"
                        >
                            {{ p.rank }}
                        </div>
                        <span class="font-medium">
                            {{ p.display_name }}
                            <span v-if="p.is_me" class="text-white/60">(You)</span>
                        </span>
                    </div>
                    <span class="font-bold text-accent-200 font-mono-party">{{ p.score }} pts</span>
                </div>
            </div>
        </GlassCard>

        <!-- Awards Section -->
        <div v-if="summary && (mostAccurate || biggestMiss)" class="grid gap-4 sm:grid-cols-2">
            <AwardCard
                v-if="mostAccurate"
                title="Most Accurate"
                :player-name="mostAccurate.display_name"
                :stat="formatYearDelta(mostAccurate.average_year_delta)"
                :description="`${mostAccurate.correct_placements}/${mostAccurate.total_placements} correct`"
            />
            <AwardCard
                v-if="biggestMiss"
                title="Biggest Miss"
                variant="warning"
                :player-name="biggestMiss.display_name"
                :stat="`${biggestMiss.year_delta} years off`"
                :description="`${biggestMiss.track.title} by ${biggestMiss.track.artist}`"
            />
        </div>

        <!-- Loading state for summary -->
        <div v-else-if="isLoadingSummary" class="text-center py-4 text-white/60">
            Loading stats...
        </div>

        <!-- AMEN Stats -->
        <AmenStatsPanel v-if="amenStats && amenStats.total_claims > 0" :stats="amenStats" />

        <!-- Game Stats -->
        <GlassCard
            v-if="gameStats && gameStats.rounds_played > 0"
            v-motion
            :initial="{ y: 20, opacity: 0 }"
            :enter="{
                y: 0,
                opacity: 1,
                transition: { delay: 200, type: 'spring', stiffness: 200, damping: 20 },
            }"
            class="p-4"
        >
            <div class="flex justify-center gap-8 text-center">
                <div>
                    <div class="text-2xl font-bold font-mono-party">
                        {{ gameStats.rounds_played }}
                    </div>
                    <div class="text-sm text-white/60 font-display">Rounds</div>
                </div>
                <div>
                    <div class="text-2xl font-bold font-mono-party">
                        {{ gameStats.overall_accuracy }}%
                    </div>
                    <div class="text-sm text-white/60 font-display">Accuracy</div>
                </div>
                <div>
                    <div class="text-2xl font-bold font-mono-party">
                        {{ gameStats.total_placements }}
                    </div>
                    <div class="text-sm text-white/60 font-display">Placements</div>
                </div>
            </div>
        </GlassCard>

        <div
            v-motion
            :initial="{ y: 20, opacity: 0 }"
            :enter="{
                y: 0,
                opacity: 1,
                transition: { delay: 600, type: 'spring', stiffness: 200, damping: 20 },
            }"
            class="flex justify-center gap-4"
        >
            <Link
                href="/room/create"
                class="btn-interactive btn-primary px-6 py-3 rounded-lg font-bold"
            >
                Play Again
            </Link>
            <Link
                href="/"
                class="btn-interactive btn-secondary px-6 py-3 rounded-lg font-bold text-white"
                >Home</Link
            >
        </div>
    </div>
</template>
