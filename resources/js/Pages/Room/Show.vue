<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import GlassCard from '../../Components/Ui/GlassCard.vue';
import BottomSheet from '../../Components/Ui/BottomSheet.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import FinishedView from '../../Components/Room/Finished/FinishedView.vue';
import LobbyView from '../../Components/Room/Lobby/LobbyView.vue';
import PlayersList from '../../Components/Room/PlayersList.vue';
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
    isPlacing,
    revealRound,
    nextRound,
    finishGame,
    leaderboard,
    // AMEN
    claimAmen,
    isClaimingAmen,
    amenClaimError,
    submitAmenGuess,
    isSubmittingAmenGuess,
    amenGuessError,
    submitAmenVote,
    isSubmittingAmenVote,
    amenVoteError,
} = useRoomState(props);

const nowMs = ref(Date.now());
let countdownIntervalId = null;

const isDetailsOpen = ref(false);

const openDetails = () => {
    isDetailsOpen.value = true;
};

const closeDetails = () => {
    isDetailsOpen.value = false;
};

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

const playersListMode = computed(() => {
    if (isInLobby.value) {
        return { showHostBadge: true, showScore: false };
    }

    return { showHostBadge: false, showScore: true };
});

const startCountdownTimer = () => {
    if (countdownIntervalId) {
        return;
    }

    countdownIntervalId = setInterval(() => {
        nowMs.value = Date.now();
    }, 250);
};

const stopCountdownTimer = () => {
    if (!countdownIntervalId) {
        return;
    }

    clearInterval(countdownIntervalId);
    countdownIntervalId = null;
};

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
    // AMEN events
    channel.listen('.amen.claimed', scheduleRefresh);
    channel.listen('.amen.guess.submitted', scheduleRefresh);
    channel.listen('.amen.vote.submitted', scheduleRefresh);
    channel.listen('.amen.resolved', scheduleRefresh);

    scheduleRefresh();
});

watch(
    () => isPlacementLocked.value,
    (locked) => {
        if (locked) {
            startCountdownTimer();
        } else {
            stopCountdownTimer();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    cleanup();

    stopCountdownTimer();

    if (window.Echo) {
        window.Echo.leave(`room.${code.value}`);
    }
});
</script>

<template>
    <AppLayout
        container-class="w-full max-w-6xl"
        main-class="flex-1 px-4 pt-6 pb-80 lg:pb-8"
        :show-footer="false"
    >
        <template #header-right>
            <div class="text-right">
                <div class="text-xs text-gray-500 dark:text-gray-400">Room code</div>
                <div class="font-mono font-bold text-accent-500 tracking-widest">{{ state.room.code }}</div>
            </div>
        </template>

        <div v-if="!state.me">
            <GlassCard class="p-8 text-center">
                <h2 class="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-50">You're not in this room</h2>
                <Link
                    :href="`/room/join?code=${state.room.code}`"
                    class="btn-interactive inline-flex items-center justify-center min-h-[44px] px-6 py-3 bg-accent-500 hover:bg-accent-600 rounded-lg font-bold transition text-white"
                >
                    Join Room {{ state.room.code }}
                </Link>
            </GlassCard>
        </div>

        <div v-else class="space-y-6">
            <div class="hidden lg:block">
                <RoomIntroCard :room="state.room" :me="state.me" />
            </div>

            <Transition name="view-fade" mode="out-in">
                <LobbyView
                    v-if="isInLobby"
                    key="lobby"
                    :state="state"
                    :is-host="isHost"
                    :can-start="canStart"
                    :select-provider="selectProvider"
                    :start-game="startGame"
                    :open-details="openDetails"
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
                    v-else-if="isInRound"
                    key="round"
                    :state="state"
                    :is-host="isHost"
                    :play-url="playUrl"
                    :copy-play-url="copyPlayUrl"
                    :remaining-seconds="remainingSeconds"
                    :is-placement-locked="isPlacementLocked"
                    :is-placing="isPlacing"
                    :place-error="placeError"
                    :place="place"
                    :reveal-round="revealRound"
                    :open-details="openDetails"
                    :is-spotify-provider="isSpotifyProvider"
                    :spotify-play-error="spotifyPlayError"
                    :is-playing-spotify="isPlayingSpotify"
                    :play-spotify-track="playSpotifyTrack"
                    :claim-amen="claimAmen"
                    :is-claiming-amen="isClaimingAmen"
                    :amen-claim-error="amenClaimError"
                    :submit-amen-guess="submitAmenGuess"
                    :is-submitting-amen-guess="isSubmittingAmenGuess"
                    :amen-guess-error="amenGuessError"
                />

                <RevealView
                    v-else-if="isInReveal"
                    key="reveal"
                    :state="state"
                    :is-host="isHost"
                    :leaderboard="leaderboard"
                    :next-round="nextRound"
                    :open-details="openDetails"
                    :submit-amen-guess="submitAmenGuess"
                    :is-submitting-amen-guess="isSubmittingAmenGuess"
                    :amen-guess-error="amenGuessError"
                    :submit-amen-vote="submitAmenVote"
                    :is-submitting-amen-vote="isSubmittingAmenVote"
                    :amen-vote-error="amenVoteError"
                />

                <FinishedView v-else-if="isFinished" key="finished" :leaderboard="leaderboard" />
            </Transition>

            <Transition name="view-fade">
                <div v-if="isHost && !isFinished" class="pt-10 hidden lg:block">
                    <div class="bg-gray-800/30 backdrop-blur rounded-2xl border border-white/10 p-6 flex justify-center">
                        <button
                            type="button"
                            class="btn-interactive px-8 py-4 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg"
                            @click="finishGame"
                        >
                            Close Game
                        </button>
                    </div>
                </div>
            </Transition>

            <BottomSheet
                :open="isDetailsOpen"
                title="Details"
                max-width-class="max-w-6xl"
                @close="closeDetails"
            >
                <div class="space-y-4">
                    <RoomIntroCard :room="state.room" :me="state.me" />

                    <GlassCard class="p-6">
                        <h2 class="text-lg font-bold mb-4">Players ({{ state.players.length }})</h2>
                        <PlayersList
                            :players="state.players"
                            :show-host-badge="playersListMode.showHostBadge"
                            :show-score="playersListMode.showScore"
                        />
                    </GlassCard>

                    <div v-if="isHost && !isFinished" class="pt-2">
                        <button
                            type="button"
                            class="btn-interactive w-full min-h-[44px] px-6 py-3 bg-red-600 text-white font-bold rounded-xl"
                            @click="finishGame"
                        >
                            Close Game
                        </button>
                    </div>
                </div>
            </BottomSheet>
        </div>
    </AppLayout>
</template>
