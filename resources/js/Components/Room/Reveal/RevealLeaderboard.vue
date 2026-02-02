<script setup>
import { computed } from 'vue';

const props = defineProps({
    leaderboard: {
        type: Array,
        required: true,
    },
    previousLeaderboard: {
        type: Array,
        default: () => [],
    },
    delay: {
        type: Number,
        default: 0,
    },
});

const leaderboardWithChanges = computed(() => {
    return props.leaderboard.map((player) => {
        const previousEntry = props.previousLeaderboard.find((p) => p.id === player.id);
        const previousRank = previousEntry?.rank ?? player.rank;
        const rankChange = previousRank - player.rank;

        return {
            ...player,
            previousRank,
            rankChange,
        };
    });
});

const getRankChangeClass = (change) => {
    if (change > 0) {
        return 'text-green-400';
    }

    if (change < 0) {
        return 'text-red-400';
    }

    return 'text-gray-500';
};

const getRankChangeIcon = (change) => {
    if (change > 0) {
        return '\u25B2';
    }

    if (change < 0) {
        return '\u25BC';
    }

    return '\u2013';
};
</script>

<template>
    <div class="space-y-2">
        <div
            v-for="(p, index) in leaderboardWithChanges"
            :key="p.id"
            v-motion
            :initial="{ x: -20, opacity: 0 }"
            :enter="{
                x: 0,
                opacity: 1,
                transition: {
                    delay: delay + index * 60,
                    type: 'spring',
                    stiffness: 300,
                    damping: 25,
                },
            }"
            class="flex items-center justify-between p-3 rounded-lg"
            :class="[
                p.rank === 1 ? 'bg-yellow-500/20 border border-yellow-500/50' : '',
                p.is_me && p.rank !== 1 ? 'bg-accent-500/20' : '',
                !p.is_me && p.rank !== 1 ? 'bg-gray-700/30' : '',
            ]"
        >
            <div class="flex items-center gap-3">
                <div
                    class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                    :class="p.rank === 1 ? 'bg-yellow-500 text-black' : 'bg-gray-600'"
                >
                    {{ p.rank }}
                </div>

                <span class="flex items-center gap-2">
                    {{ p.display_name }}

                    <span
                        v-if="p.rank === 1"
                        class="animate-crown-bounce inline-block"
                        title="Leader"
                    >
                        <span class="text-lg">👑</span>
                    </span>
                </span>

                <span
                    v-if="p.rankChange !== 0"
                    class="text-xs font-bold flex items-center gap-0.5"
                    :class="getRankChangeClass(p.rankChange)"
                >
                    <span>{{ getRankChangeIcon(p.rankChange) }}</span>
                    <span>{{ Math.abs(p.rankChange) }}</span>
                </span>
            </div>

            <span class="font-bold text-accent-400 tabular-nums">{{ p.score }} pts</span>
        </div>
    </div>
</template>
