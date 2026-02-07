<script setup>
import { computed } from 'vue';

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    remainingSeconds: {
        type: Number,
        default: 0,
    },
    placementTimeRemaining: {
        type: Number,
        default: 0,
    },
    isPlacementLocked: {
        type: Boolean,
        default: false,
    },
});

const phase = computed(() => props.state.room?.state ?? '');

const phaseLabel = computed(() => {
    if (phase.value === 'lobby') {
        return 'Lobby';
    }

    if (phase.value === 'in_round') {
        return 'Round';
    }

    if (phase.value === 'reveal') {
        return 'Reveal';
    }

    if (phase.value === 'finished') {
        return 'Finished';
    }

    return 'Game';
});

const timerLabel = computed(() => {
    if (phase.value !== 'in_round') {
        return '';
    }

    if (props.remainingSeconds > 0) {
        return props.isPlacementLocked ? 'Locked' : 'Starts';
    }

    if (props.placementTimeRemaining > 0) {
        return 'Time';
    }

    return '';
});

const timerValue = computed(() => {
    if (phase.value !== 'in_round') {
        return null;
    }

    if (props.remainingSeconds > 0) {
        return props.remainingSeconds;
    }

    if (props.placementTimeRemaining > 0) {
        return props.placementTimeRemaining;
    }

    return null;
});

const placements = computed(() => {
    const placed = props.state.round?.placements_count ?? null;
    const total = props.state.round?.players_count ?? null;

    if (phase.value !== 'in_round' || placed === null || total === null) {
        return null;
    }

    return { placed, total };
});

const ready = computed(() => {
    const count = props.state.room?.ready_count ?? null;
    const total = props.state.room?.ready_total ?? null;

    if (phase.value !== 'lobby' || count === null || total === null || total <= 0) {
        return null;
    }

    return { count, total };
});
</script>

<template>
    <div class="neon-hud rounded-2xl px-4 py-3 backdrop-blur">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <div class="font-display text-sm text-white/90">
                        Breakster
                    </div>
                    <div class="px-2 py-1 rounded-full border border-white/10 bg-white/5 text-xs text-white/80 font-display">
                        {{ phaseLabel }}
                    </div>
                </div>

                <div class="mt-1 flex items-center gap-3 text-xs text-white/70">
                    <div v-if="state.round?.round_number" class="font-mono-party">
                        R{{ state.round.round_number }}
                    </div>

                    <div v-if="ready" class="font-mono-party">
                        Ready {{ ready.count }}/{{ ready.total }}
                    </div>

                    <div v-if="placements" class="font-mono-party">
                        Placed {{ placements.placed }}/{{ placements.total }}
                    </div>
                </div>
            </div>

            <div v-if="timerValue !== null" class="shrink-0 text-right">
                <div class="text-[10px] uppercase tracking-widest text-white/60 font-display">
                    {{ timerLabel }}
                </div>
                <div
                    class="font-mono-party text-2xl leading-none"
                    :class="timerValue <= 5 ? 'text-amber-300' : 'text-accent-200'"
                >
                    {{ timerValue }}
                </div>
            </div>
        </div>
    </div>
</template>
