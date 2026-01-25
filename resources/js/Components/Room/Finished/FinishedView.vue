<script setup>
import confetti from 'canvas-confetti';
import { onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';

import GlassCard from '../../Ui/GlassCard.vue';

const props = defineProps({
    leaderboard: {
        type: Array,
        required: true,
    },
});

onMounted(() => {
    const isWinner = props.leaderboard[0]?.is_me;

    if (isWinner) {
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 },
            colors: ['#00d4ff', '#ff6b6b', '#ffd93d', '#6bcb77'],
        });

        setTimeout(() => {
            confetti({
                particleCount: 50,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: ['#00d4ff', '#ff6b6b', '#ffd93d'],
            });
            confetti({
                particleCount: 50,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: ['#00d4ff', '#ff6b6b', '#ffd93d'],
            });
        }, 250);
    }
});
</script>

<template>
    <div class="space-y-6">
        <GlassCard class="p-8 text-center">
            <h1
                v-motion
                :initial="{ scale: 0.8, opacity: 0 }"
                :enter="{ scale: 1, opacity: 1, transition: { type: 'spring', stiffness: 200, damping: 15 } }"
                class="text-4xl font-bold mb-6"
            >
                Game Over!
            </h1>

            <div
                v-if="leaderboard[0]"
                v-motion
                :initial="{ scale: 0.5, opacity: 0 }"
                :enter="{ scale: 1, opacity: 1, transition: { delay: 200, type: 'spring', stiffness: 180, damping: 12 } }"
                class="inline-block p-6 bg-yellow-500/20 rounded-2xl border border-yellow-500 mb-4 animate-pulse-glow"
            >
                <div class="text-yellow-400 text-sm mb-2">Winner</div>
                <div class="text-3xl font-bold">{{ leaderboard[0].display_name }}</div>
                <div class="text-2xl text-yellow-400 tabular-nums">{{ leaderboard[0].score }} points</div>
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
                    :enter="{ x: 0, opacity: 1, transition: { delay: 400 + index * 80, type: 'spring', stiffness: 300, damping: 25 } }"
                    class="flex items-center justify-between p-4 rounded-lg"
                    :class="
                        p.rank === 1
                            ? 'bg-yellow-500/20 border border-yellow-500'
                            : p.is_me
                                ? 'bg-accent-500/20'
                                : 'bg-gray-700/30'
                    "
                >
                    <div class="flex items-center gap-3">
                        <div
                            v-motion
                            :initial="{ scale: 0 }"
                            :enter="{ scale: 1, transition: { delay: 500 + index * 80, type: 'spring', stiffness: 400, damping: 15 } }"
                            class="w-10 h-10 rounded-full flex items-center justify-center font-bold"
                            :class="p.rank === 1 ? 'bg-yellow-500 text-black' : 'bg-gray-600'"
                        >
                            {{ p.rank }}
                        </div>
                        <span class="font-medium">
                            {{ p.display_name }}
                            <span v-if="p.is_me" class="text-gray-400">(You)</span>
                        </span>
                    </div>
                    <span class="font-bold text-accent-400 tabular-nums">{{ p.score }} pts</span>
                </div>
            </div>
        </GlassCard>

        <div
            v-motion
            :initial="{ y: 20, opacity: 0 }"
            :enter="{ y: 0, opacity: 1, transition: { delay: 600, type: 'spring', stiffness: 200, damping: 20 } }"
            class="flex justify-center gap-4"
        >
            <Link href="/room/create" class="btn-interactive px-6 py-3 bg-accent-600 hover:bg-accent-700 rounded-lg font-bold">
                Play Again
            </Link>
            <Link href="/" class="btn-interactive px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-bold">Home</Link>
        </div>
    </div>
</template>
