<script setup>
import { Link } from '@inertiajs/vue3';
import GlassCard from '../../Ui/GlassCard.vue';

defineProps({
    leaderboard: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div class="space-y-6">
        <GlassCard class="p-8 text-center">
            <h1 class="text-4xl font-bold mb-6">Game Over!</h1>

            <div
                v-if="leaderboard[0]"
                class="inline-block p-6 bg-yellow-500/20 rounded-2xl border border-yellow-500 mb-4"
            >
                <div class="text-yellow-400 text-sm mb-2">Winner</div>
                <div class="text-3xl font-bold">{{ leaderboard[0].display_name }}</div>
                <div class="text-2xl text-yellow-400">{{ leaderboard[0].score }} points</div>
            </div>
        </GlassCard>

        <GlassCard class="p-8">
            <h2 class="text-xl font-bold mb-4 text-center">Final Standings</h2>
            <div class="max-w-md mx-auto space-y-2">
                <div
                    v-for="p in leaderboard"
                    :key="p.id"
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
                    <span class="font-bold text-accent-400">{{ p.score }} pts</span>
                </div>
            </div>
        </GlassCard>

        <div class="flex justify-center gap-4">
            <Link href="/room/create" class="px-6 py-3 bg-accent-600 hover:bg-accent-700 rounded-lg font-bold transition">
                Play Again
            </Link>
            <Link href="/" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-bold transition">Home</Link>
        </div>
    </div>
</template>
