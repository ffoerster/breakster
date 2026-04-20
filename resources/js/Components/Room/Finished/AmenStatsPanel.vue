<script setup>
import GlassCard from "../../Ui/GlassCard.vue";

defineProps({
    stats: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <GlassCard
        v-motion
        :initial="{ y: 20, opacity: 0 }"
        :enter="{
            y: 0,
            opacity: 1,
            transition: { delay: 100, type: 'spring', stiffness: 200, damping: 20 },
        }"
        class="p-6"
    >
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2 font-display">
            <span class="text-accent-300">AMEN</span>
            Stats
        </h3>

        <div v-if="stats.by_player.length === 0" class="text-white/60 text-center py-4">
            No AMEN claims this game
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="(player, index) in stats.by_player"
                :key="player.player_id"
                v-motion
                :initial="{ x: -20, opacity: 0 }"
                :enter="{
                    x: 0,
                    opacity: 1,
                    transition: {
                        delay: 150 + index * 50,
                        type: 'spring',
                        stiffness: 300,
                        damping: 25,
                    },
                }"
                class="flex items-center justify-between p-3 rounded-lg"
                :class="
                    index === 0 && player.net_points > 0
                        ? 'bg-accent-500/10 border border-accent-500/30'
                        : 'bg-white/5 border border-accent-500/15'
                "
            >
                <div class="flex items-center gap-3">
                    <div class="font-medium">{{ player.display_name }}</div>
                    <div class="text-sm text-white/60">
                        {{ player.claims }} claim{{ player.claims !== 1 ? "s" : "" }}
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex gap-2 text-sm">
                        <span v-if="player.confirmed > 0" class="text-green-400" title="Confirmed">
                            {{ player.confirmed }} confirmed
                        </span>
                        <span v-if="player.rejected > 0" class="text-red-400" title="Rejected">
                            {{ player.rejected }} rejected
                        </span>
                        <span v-if="player.ties > 0" class="text-white/55" title="Ties">
                            {{ player.ties }} tie{{ player.ties !== 1 ? "s" : "" }}
                        </span>
                    </div>

                    <div
                        class="font-bold font-mono-party min-w-15 text-right"
                        :class="{
                            'text-green-400': player.net_points > 0,
                            'text-red-400': player.net_points < 0,
                            'text-white/55': player.net_points === 0,
                        }"
                    >
                        {{ player.net_points > 0 ? "+" : "" }}{{ player.net_points }} pts
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-white/10 text-sm text-white/60 text-center">
            {{ stats.total_claims }} total AMEN claim{{ stats.total_claims !== 1 ? "s" : "" }}
        </div>
    </GlassCard>
</template>
