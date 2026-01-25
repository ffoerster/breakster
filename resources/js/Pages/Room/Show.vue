<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import GlassCard from '../../Components/Ui/GlassCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import FinishedView from '../../Components/Room/Finished/FinishedView.vue';
import LobbyView from '../../Components/Room/Lobby/LobbyView.vue';
import RevealView from '../../Components/Room/Reveal/RevealView.vue';
import RoomIntroCard from '../../Components/Room/RoomIntroCard.vue';
import RoundView from '../../Components/Room/Round/RoundView.vue';
import { useRoomState } from '../../composables/useRoomState';
import { useSpotifyHostPlayback } from '../../composables/useSpotifyHostPlayback';

const props = defineProps({
    room: {
        type: Object,
        required: true,
    },
    me: {
        type: Object,
        default: null,
    },
    players: {
        type: Array,
        required: true,
    },
    providers: {
        type: Array,
        required: true,
    },
    round: {
        type: Object,
        default: null,
    },
    timeline: {
        type: Array,
        default: null,
    },
});

const {
    state,
    code,
    isHost,
    isInLobby,
    isInRound,
    isInReveal,
    isFinished,
    refreshState,
    scheduleRefresh,
    cleanup,
    playUrl,
    copyPlayUrl,
    selectProvider,
    startGame,
    canStart,
    place,
    placeError,
    revealRound,
    nextRound,
    finishGame,
    leaderboard,
} = useRoomState(props);

const nowMs = ref(Date.now());
let countdownIntervalId = null;

const placementUnlocksAtMs = computed(() => {
    const iso = state.round?.placement_unlocks_at;
    if (!iso) {
        return null;
    }

    const ms = new Date(iso).getTime();

    return Number.isFinite(ms) ? ms : null;
});

const remainingSeconds = computed(() => {
    if (!placementUnlocksAtMs.value) {
        return 0;
    }

    const remainingMs = placementUnlocksAtMs.value - nowMs.value;
    if (remainingMs <= 0) {
        return 0;
    }

    return Math.ceil(remainingMs / 1000);
});

const isPlacementLocked = computed(() => isInRound.value && remainingSeconds.value > 0);

const {
    spotifyDevices,
    spotifyError,
    isLoadingDevices,
    hasSpotifyToken,
    isSpotifyProvider,
    selectedDeviceId,
    loadSpotifyDevices,
    selectSpotifyDevice,
    connectSpotify,
    spotifyPlayError,
    isPlayingSpotify,
    playSpotifyTrack,
} = useSpotifyHostPlayback({ state, isHost, code, refreshState });

let channel = null;

onMounted(() => {
    if (!state.me) {
        return;
    }

    if (!window.Echo) {
        return;
    }

    channel = window.Echo.channel(`room.${code.value}`);

    channel.listen('.players.updated', scheduleRefresh);
    channel.listen('.round.started', scheduleRefresh);
    channel.listen('.placement.submitted', scheduleRefresh);
    channel.listen('.round.revealed', scheduleRefresh);
    channel.listen('.room.state.changed', scheduleRefresh);

    scheduleRefresh();

    countdownIntervalId = setInterval(() => {
        nowMs.value = Date.now();
    }, 200);
});

onBeforeUnmount(() => {
    cleanup();

    if (countdownIntervalId) {
        clearInterval(countdownIntervalId);
    }

    if (window.Echo) {
        window.Echo.leave(`room.${code.value}`);
    }
});
</script>

<template>
    <AppLayout container-class="w-full max-w-6xl">
        <template #header-right>
            <div class="text-right">
                <div class="text-xs text-gray-400">Room code</div>
                <div class="font-mono font-bold text-purple-300 tracking-widest">{{ state.room.code }}</div>
            </div>
        </template>

        <div v-if="!state.me">
            <GlassCard class="p-8 text-center">
                <h2 class="text-2xl font-bold mb-4">You're not in this room</h2>
                <Link
                    :href="`/room/join?code=${state.room.code}`"
                    class="inline-block px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-bold transition"
                >
                    Join Room {{ state.room.code }}
                </Link>
            </GlassCard>
        </div>

        <div v-else class="space-y-6">
            <RoomIntroCard :room="state.room" :me="state.me" />

            <LobbyView
                v-if="isInLobby"
                :state="state"
                :is-host="isHost"
                :can-start="canStart"
                :select-provider="selectProvider"
                :start-game="startGame"
                :is-spotify-provider="isSpotifyProvider"
                :has-spotify-token="hasSpotifyToken"
                :spotify-devices="spotifyDevices"
                :spotify-error="spotifyError"
                :is-loading-devices="isLoadingDevices"
                :selected-device-id="selectedDeviceId"
                :connect-spotify="connectSpotify"
                :load-spotify-devices="loadSpotifyDevices"
                :select-spotify-device="selectSpotifyDevice"
            />

            <RoundView
                v-if="isInRound"
                :state="state"
                :is-host="isHost"
                :play-url="playUrl"
                :copy-play-url="copyPlayUrl"
                :remaining-seconds="remainingSeconds"
                :is-placement-locked="isPlacementLocked"
                :place-error="placeError"
                :place="place"
                :reveal-round="revealRound"
                :is-spotify-provider="isSpotifyProvider"
                :spotify-play-error="spotifyPlayError"
                :is-playing-spotify="isPlayingSpotify"
                :play-spotify-track="playSpotifyTrack"
            />

            <RevealView
                v-if="isInReveal"
                :state="state"
                :is-host="isHost"
                :leaderboard="leaderboard"
                :next-round="nextRound"
            />

            <FinishedView v-if="isFinished" :leaderboard="leaderboard" />

            <div v-if="isHost && !isFinished" class="pt-10">
                <div class="bg-gray-800/30 backdrop-blur rounded-2xl border border-white/10 p-6 flex justify-center">
                    <button
                        type="button"
                        class="px-8 py-4 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg transition"
                        @click="finishGame"
                    >
                        Close Game
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
