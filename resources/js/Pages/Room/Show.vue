<script setup>
import { Link } from '@inertiajs/vue3';
import { useIntervalFn, usePreferredReducedMotion } from '@vueuse/core';
import { useDrag } from '@vueuse/gesture';
import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { useHaptics } from '../../composables/useHaptics';

import GlassCard from '../../Components/Ui/GlassCard.vue';
import BottomSheet from '../../Components/Ui/BottomSheet.vue';
import ConfirmDialog from '../../Components/Ui/ConfirmDialog.vue';
import SoundToggle from '../../Components/Ui/SoundToggle.vue';
import HapticToggle from '../../Components/Ui/HapticToggle.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import FinishedView from '../../Components/Room/Finished/FinishedView.vue';
import LobbyView from '../../Components/Room/Lobby/LobbyView.vue';
import PlayersList from '../../Components/Room/PlayersList.vue';
import RevealView from '../../Components/Room/Reveal/RevealView.vue';
import RoomIntroCard from '../../Components/Room/RoomIntroCard.vue';
import RoundView from '../../Components/Room/Round/RoundView.vue';
import { useGameSounds } from '../../composables/useGameSounds';
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
    copiedPlayUrl,
    selectProvider,
    startGame,
    canStart,
    place,
    placeError,
    isPlacing,
    revealRound,
    nextRound,
    finishGame,
    openFinishGameConfirm,
    isFinishGameConfirmOpen,
    isFinishingGame,
    finishGameError,
    cancelFinishGame,
    leaderboard,
    previousLeaderboard,
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
    // Ready
    markReady,
    isMarkingReady,
    markReadyError,
    // Placement timer
    togglePlacementTimer,
    isTogglingPlacementTimer,
    // Summary
    summary,
    isLoadingSummary,
} = useRoomState(props);

const nowMs = ref(Date.now());
const { pause: pauseCountdownTimer, resume: resumeCountdownTimer } = useIntervalFn(
    () => {
        nowMs.value = Date.now();
    },
    250,
    { immediate: false },
);

const isDetailsOpen = ref(false);

const openDetails = () => {
    isDetailsOpen.value = true;
};

const closeDetails = () => {
    isDetailsOpen.value = false;
};

// Pull-to-refresh
const haptics = useHaptics();
const pullContainerEl = ref(null);
const pullDistance = ref(0);
const isPulling = ref(false);
const isRefreshing = ref(false);
const PULL_THRESHOLD = 80;

useDrag(
    ({ movement: [, my], first, last }) => {
        // Only activate at top of page
        if (window.scrollY > 0) {
            return;
        }

        // Don't start new pull during refresh
        if (isRefreshing.value) {
            return;
        }

        if (first) {
            isPulling.value = true;
        }

        pullDistance.value = Math.max(0, Math.min(my, 120));

        if (last) {
            if (pullDistance.value >= PULL_THRESHOLD) {
                haptics.medium();
                isRefreshing.value = true;
                refreshState().finally(() => {
                    isRefreshing.value = false;
                });
            }
            pullDistance.value = 0;
            isPulling.value = false;
        }
    },
    {
        target: pullContainerEl,
        axis: 'y',
        filterTaps: true,
    },
);

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

const placementEndsAtMs = computed(() => {
    const iso = state.round?.placement_ends_at;
    if (!iso) {
        return null;
    }

    const ms = new Date(iso).getTime();

    return Number.isFinite(ms) ? ms : null;
});

const placementTimeRemainingSeconds = computed(() => {
    if (!placementEndsAtMs.value) {
        return 0;
    }

    const remainingMs = placementEndsAtMs.value - nowMs.value;
    if (remainingMs <= 0) {
        return 0;
    }

    return Math.ceil(remainingMs / 1000);
});

const isPlacementTimeExpired = computed(
    () => isInRound.value && placementEndsAtMs.value && nowMs.value >= placementEndsAtMs.value,
);

const isPlacementLocked = computed(
    () => isInRound.value && (remainingSeconds.value > 0 || isPlacementTimeExpired.value),
);

const prefersReducedMotion = usePreferredReducedMotion();

const scrollToTop = async () => {
    await nextTick();

    window.scrollTo({
        top: 0,
        behavior: prefersReducedMotion.value ? 'auto' : 'smooth',
    });
};

const playersListMode = computed(() => {
    if (isInLobby.value) {
        return { showHostBadge: true, showScore: false };
    }

    return { showHostBadge: false, showScore: true };
});

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

// Game sounds
const { playAmen, playClick, playReveal, playCorrect, playWrong, unlockAudio, isSoundEnabled } = useGameSounds();

// Provide sound functions to child components
provide('gameSounds', {
    playAmen,
    playClick,
    playReveal,
    playCorrect,
    playWrong,
});

let channel = null;

onMounted(() => {
    // Unlock audio on first user interaction (required for mobile)
    const handleFirstInteraction = () => {
        unlockAudio();
        document.removeEventListener('click', handleFirstInteraction);
        document.removeEventListener('touchstart', handleFirstInteraction);
    };

    document.addEventListener('click', handleFirstInteraction, { once: true });
    document.addEventListener('touchstart', handleFirstInteraction, { once: true });

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
    channel.listen('.round.revealed', () => {
        playReveal();
        scheduleRefresh();
    });
    channel.listen('.room.state.changed', scheduleRefresh);
    // AMEN events
    channel.listen('.amen.claimed', scheduleRefresh);
    channel.listen('.amen.guess.submitted', scheduleRefresh);
    channel.listen('.amen.vote.submitted', scheduleRefresh);
    channel.listen('.amen.resolved', scheduleRefresh);
    // Ready events
    channel.listen('.player.ready', scheduleRefresh);
    // Placement timeout
    channel.listen('.placement.timeout.resolved', scheduleRefresh);

    scheduleRefresh();
});

// Keep countdown timer running during both unlock and placement phases
const shouldRunTimer = computed(
    () => isInRound.value && (remainingSeconds.value > 0 || placementTimeRemainingSeconds.value > 0),
);

watch(
    shouldRunTimer,
    (shouldRun) => {
        if (shouldRun) {
            resumeCountdownTimer();
        } else {
            pauseCountdownTimer();
        }
    },
    { immediate: true },
);

watch(
    () => state.round?.id,
    async (id, previousId) => {
        if (!id || id === previousId) {
            return;
        }

        await scrollToTop();
    },
);

watch(
    () => state.room.state,
    async (roomState, previousRoomState) => {
        if (roomState !== 'reveal' || roomState === previousRoomState) {
            return;
        }

        await scrollToTop();
    },
);

// Play sound when AMEN is resolved
watch(
    () => state.round?.amen?.resolved,
    (resolved, wasResolved) => {
        if (resolved && !wasResolved) {
            if (state.round?.amen?.correct) {
                playCorrect();
            } else {
                playWrong();
            }
        }
    },
);

onBeforeUnmount(() => {
    cleanup();

    pauseCountdownTimer();

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

        <div v-else ref="pullContainerEl" class="space-y-6">
            <!-- Pull-to-refresh indicator -->
            <div
                v-if="isPulling || isRefreshing"
                class="fixed top-0 left-0 right-0 flex justify-center pt-4 z-50 pointer-events-none"
                :style="{ transform: `translateY(${pullDistance - 40}px)` }"
            >
                <div
                    class="w-10 h-10 rounded-full bg-gray-800/90 border border-accent-500/50 flex items-center justify-center shadow-lg"
                >
                    <svg
                        v-if="isRefreshing"
                        class="w-5 h-5 text-accent-400 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                    </svg>
                    <svg
                        v-else
                        class="w-5 h-5 text-accent-400 transition-transform duration-200"
                        :class="pullDistance >= PULL_THRESHOLD ? 'rotate-180' : ''"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                </div>
            </div>

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
                    :mark-ready="markReady"
                    :is-marking-ready="isMarkingReady"
                    :mark-ready-error="markReadyError"
                    :toggle-placement-timer="togglePlacementTimer"
                    :is-toggling-placement-timer="isTogglingPlacementTimer"
                />

                <RoundView
                    v-else-if="isInRound"
                    key="round"
                    :state="state"
                    :is-host="isHost"
                    :play-url="playUrl"
                    :copy-play-url="copyPlayUrl"
                    :copied-play-url="copiedPlayUrl"
                    :remaining-seconds="remainingSeconds"
                    :is-placement-locked="isPlacementLocked"
                    :placement-time-remaining="placementTimeRemainingSeconds"
                    :is-placement-time-expired="isPlacementTimeExpired"
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
                    :previous-leaderboard="previousLeaderboard"
                    :next-round="nextRound"
                    :open-details="openDetails"
                    :submit-amen-guess="submitAmenGuess"
                    :is-submitting-amen-guess="isSubmittingAmenGuess"
                    :amen-guess-error="amenGuessError"
                    :submit-amen-vote="submitAmenVote"
                    :is-submitting-amen-vote="isSubmittingAmenVote"
                    :amen-vote-error="amenVoteError"
                />

                <FinishedView
                    v-else-if="isFinished"
                    key="finished"
                    :leaderboard="leaderboard"
                    :summary="summary"
                    :is-loading-summary="isLoadingSummary"
                />
            </Transition>

            <Transition name="view-fade">
                <div v-if="isHost && !isFinished" class="pt-10 hidden lg:block">
                    <div class="bg-gray-800/30 backdrop-blur rounded-2xl border border-white/10 p-6 flex justify-center">
                        <button
                            type="button"
                            class="btn-interactive px-8 py-4 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg"
                            @click="openFinishGameConfirm"
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

                    <GlassCard class="p-4">
                        <SoundToggle />
                    </GlassCard>

                    <GlassCard class="p-4">
                        <HapticToggle />
                    </GlassCard>

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
                            @click="openFinishGameConfirm"
                        >
                            Close Game
                        </button>
                    </div>
                </div>
            </BottomSheet>

            <ConfirmDialog
                :open="isFinishGameConfirmOpen"
                title="Finish game?"
                message="Players will no longer be able to join this room."
                confirm-label="Finish game"
                cancel-label="Keep playing"
                danger
                :is-confirming="isFinishingGame"
                :error="finishGameError"
                @confirm="finishGame"
                @cancel="cancelFinishGame"
            />
        </div>
    </AppLayout>
</template>
