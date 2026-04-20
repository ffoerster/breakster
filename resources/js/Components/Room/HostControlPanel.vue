<script setup>
import { computed } from "vue";

import GlassCard from "../Ui/GlassCard.vue";

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    isHost: {
        type: Boolean,
        required: true,
    },

    // Lobby actions
    canStart: {
        type: Boolean,
        required: true,
    },
    startGame: {
        type: Function,
        required: true,
    },
    isStarting: {
        type: Boolean,
        default: false,
    },
    startError: {
        type: String,
        default: "",
    },

    // Round actions
    revealRound: {
        type: Function,
        required: true,
    },

    // Reveal actions
    nextRound: {
        type: Function,
        required: true,
    },

    // Global actions
    openFinishGameConfirm: {
        type: Function,
        required: true,
    },

    // Optional: Spotify retry
    isSpotifyProvider: {
        type: Boolean,
        default: false,
    },
    playSpotifyTrack: {
        type: Function,
        default: null,
    },
    isPlayingSpotify: {
        type: Boolean,
        default: false,
    },
    spotifyPlayError: {
        type: String,
        default: "",
    },
});

const isInLobby = computed(() => props.state.room.state === "lobby");
const isInRound = computed(() => props.state.room.state === "in_round");
const isInReveal = computed(() => props.state.room.state === "reveal");

const phaseLabel = computed(() => {
    if (isInLobby.value) {
        return "Lobby";
    }

    if (isInRound.value) {
        return "Round";
    }

    if (isInReveal.value) {
        return "Reveal";
    }

    return "";
});

const phaseColor = computed(() => {
    if (isInLobby.value) {
        return "bg-accent-500/20 text-accent-200 border-accent-500/40";
    }

    if (isInRound.value) {
        return "bg-amber-500/20 text-amber-200 border-amber-500/40";
    }

    if (isInReveal.value) {
        return "bg-green-500/20 text-green-200 border-green-500/40";
    }

    return "bg-white/10 text-white/70 border-white/20";
});

const allGuestsReady = computed(() => {
    const total = props.state.room.ready_total ?? 0;
    const ready = props.state.room.ready_count ?? 0;

    return total > 0 && ready >= total;
});

const placementsProgress = computed(() => {
    const total = props.state.round?.players_count ?? 0;
    if (total <= 0) {
        return 0;
    }

    const placed = props.state.round?.placements_count ?? 0;

    return Math.min(100, Math.max(0, Math.round((placed / total) * 100)));
});
</script>

<template>
    <GlassCard v-if="isHost" class="p-5 space-y-5">
        <!-- Phase Badge -->
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-white/80 font-display">Host Controls</h3>
            <span class="px-2.5 py-1 rounded-full text-xs font-bold border" :class="phaseColor">
                {{ phaseLabel }}
            </span>
        </div>

        <!-- Lobby Status -->
        <div v-if="isInLobby" class="space-y-4">
            <div class="flex items-center justify-between text-sm">
                <span class="text-white/60">Players</span>
                <span class="font-mono-party text-white/90">{{ state.players.length }}</span>
            </div>
            <div
                v-if="state.room.ready_total > 0"
                class="flex items-center justify-between text-sm"
            >
                <span class="text-white/60">Ready</span>
                <span
                    class="font-mono-party"
                    :class="allGuestsReady ? 'text-green-400' : 'text-accent-200'"
                >
                    {{ state.room.ready_count ?? 0 }} / {{ state.room.ready_total }}
                </span>
            </div>

            <button
                type="button"
                class="btn-interactive w-full py-3 px-4 font-bold rounded-lg transition-all duration-200"
                :class="[
                    canStart ? 'btn-success' : 'bg-white/10 text-white/40 cursor-not-allowed',
                    allGuestsReady && canStart ? 'pulse-ready' : '',
                ]"
                :disabled="!canStart || isStarting"
                @click="startGame"
            >
                <span v-if="isStarting" class="flex items-center justify-center gap-2">
                    <span
                        class="w-4 h-4 rounded-full border-2 border-white/30 border-t-white animate-spin"
                    />
                    Starting...
                </span>
                <span v-else>Start Game</span>
            </button>
            <p v-if="startError" class="text-sm text-red-400 animate-shake">
                {{ startError }}
            </p>
            <p v-if="!state.room.playback_provider" class="text-xs text-white/50">
                Select a listening app to start
            </p>
        </div>

        <!-- Round Status -->
        <div v-else-if="isInRound" class="space-y-4">
            <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-white/60">Placements</span>
                    <span class="font-mono-party text-accent-200">
                        {{ state.round?.placements_count ?? 0 }} /
                        {{ state.round?.players_count ?? 0 }}
                    </span>
                </div>
                <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                    <div
                        class="h-full bg-accent-500/70 transition-all duration-300"
                        :style="{ width: `${placementsProgress}%` }"
                    />
                </div>
            </div>

            <!-- Spotify Retry -->
            <button
                v-if="isSpotifyProvider && playSpotifyTrack"
                type="button"
                class="btn-interactive w-full py-2 px-4 rounded-lg text-sm transition-all duration-200 bg-white/10 text-white/70 hover:bg-white/15"
                :disabled="isPlayingSpotify"
                @click="playSpotifyTrack"
            >
                <span v-if="isPlayingSpotify" class="flex items-center justify-center gap-2">
                    <span
                        class="w-3 h-3 rounded-full border-2 border-white/30 border-t-white animate-spin"
                    />
                    Playing...
                </span>
                <span v-else>Retry Spotify</span>
            </button>
            <p v-if="spotifyPlayError" class="text-sm text-red-400 animate-shake">
                {{ spotifyPlayError }}
            </p>

            <button
                type="button"
                class="btn-interactive btn-warning w-full py-3 px-4 rounded-lg font-bold transition-all duration-200"
                @click="revealRound"
            >
                Reveal Answers
            </button>
        </div>

        <!-- Reveal Status -->
        <div v-else-if="isInReveal" class="space-y-4">
            <div class="flex items-center justify-between text-sm">
                <span class="text-white/60">Round</span>
                <span class="font-mono-party text-white/90">{{ state.round?.round_number }}</span>
            </div>

            <button
                type="button"
                class="btn-interactive btn-success w-full py-3 px-4 rounded-lg font-bold transition-all duration-200"
                @click="nextRound"
            >
                Next Round
            </button>
        </div>

        <!-- Close Game -->
        <button
            type="button"
            class="w-full py-2 px-4 rounded-lg text-sm text-white/40 hover:text-red-400 hover:bg-red-500/10 transition-all duration-200"
            @click="openFinishGameConfirm"
        >
            Close Game
        </button>
    </GlassCard>
</template>
