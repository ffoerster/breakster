<script setup>
import { computed } from 'vue';

import GlassCard from '../Ui/GlassCard.vue';

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
        default: '',
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
    finishGame: {
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
        default: '',
    },
});

const isInLobby = computed(() => props.state.room.state === 'lobby');
const isInRound = computed(() => props.state.room.state === 'in_round');
const isInReveal = computed(() => props.state.room.state === 'reveal');
const isFinished = computed(() => props.state.room.state === 'finished');
</script>

<template>
    <GlassCard v-if="isHost && !isFinished" class="p-6 bg-amber-500/10 border-amber-500/30">
        <h3 class="text-lg font-bold mb-4 text-amber-400">Host Controls</h3>

        <!-- Lobby: Start Game -->
        <div v-if="isInLobby" class="space-y-3">
            <button
                type="button"
                class="btn-interactive w-full py-3 px-4 font-bold rounded-lg transition-all duration-200"
                :class="
                    canStart
                        ? 'bg-green-600 hover:bg-green-700 text-white'
                        : 'bg-gray-700 text-gray-500 cursor-not-allowed'
                "
                :disabled="!canStart || isStarting"
                @click="startGame"
            >
                <span v-if="isStarting" class="flex items-center justify-center gap-2">
                    <span class="w-4 h-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                    <span>Starting...</span>
                </span>
                <span v-else>Start Game</span>
            </button>
            <p v-if="startError" class="text-sm text-red-400 animate-shake">
                {{ startError }}
            </p>
            <p v-if="!state.room.playback_provider" class="text-xs text-gray-400">
                Select a playback provider to start
            </p>
        </div>

        <!-- In Round: Ready count + Reveal -->
        <div v-else-if="isInRound" class="space-y-4">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-400">Players ready:</span>
                <span class="font-bold text-blue-400">
                    {{ state.round?.ready_count ?? 0 }} / {{ state.round?.players_count ?? 0 }}
                </span>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-400">Placements:</span>
                <span class="font-bold text-green-400">
                    {{ state.round?.placements_count ?? 0 }} / {{ state.round?.players_count ?? 0 }}
                </span>
            </div>

            <!-- Spotify Retry (optional) -->
            <button
                v-if="isSpotifyProvider && playSpotifyTrack"
                type="button"
                class="btn-interactive w-full py-2 px-4 bg-green-700 hover:bg-green-600 rounded-lg text-sm transition-all duration-200"
                :disabled="isPlayingSpotify"
                @click="playSpotifyTrack"
            >
                <span v-if="isPlayingSpotify" class="flex items-center justify-center gap-2">
                    <span class="w-4 h-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                    <span>Playing...</span>
                </span>
                <span v-else>Retry Spotify</span>
            </button>
            <p v-if="spotifyPlayError" class="text-sm text-red-400 animate-shake">
                {{ spotifyPlayError }}
            </p>

            <button
                type="button"
                class="btn-interactive w-full py-3 px-4 bg-yellow-600 hover:bg-yellow-700 rounded-lg font-bold transition-all duration-200"
                @click="revealRound"
            >
                Reveal Answers
            </button>
        </div>

        <!-- Reveal: Next Round -->
        <div v-else-if="isInReveal" class="space-y-3">
            <button
                type="button"
                class="btn-interactive w-full py-3 px-4 bg-green-600 hover:bg-green-700 rounded-lg font-bold transition-all duration-200"
                @click="nextRound"
            >
                Next Round
            </button>
        </div>

        <!-- Always show Close Game -->
        <button
            type="button"
            class="btn-interactive w-full mt-4 py-2 px-4 bg-red-600/50 hover:bg-red-600 rounded-lg text-sm transition-all duration-200"
            @click="finishGame"
        >
            Close Game
        </button>
    </GlassCard>
</template>
