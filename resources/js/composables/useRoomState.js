import { useClipboard, useConfirmDialog, useDebounceFn, useDocumentVisibility, useOnline } from '@vueuse/core';
import { useAxios } from '@vueuse/integrations/useAxios';
import { computed, ref, shallowReactive, watch } from 'vue';
import { toast } from 'vue-sonner';

export function useRoomState(props) {
    const state = shallowReactive({
        room: props.room,
        me: props.me,
        players: props.players,
        providers: props.providers,
        round: props.round,
        timeline: props.timeline,
    });

    const applyState = (data) => {
        state.room = data.room;
        state.me = data.me;
        state.players = data.players;
        state.providers = data.providers;
        state.round = data.round;
        state.timeline = data.timeline;
    };

    const isHost = computed(() => Boolean(state.me?.is_host));
    const isInLobby = computed(() => state.room.state === 'lobby');
    const isInRound = computed(() => state.room.state === 'in_round');
    const isInReveal = computed(() => state.room.state === 'reveal');
    const isFinished = computed(() => state.room.state === 'finished');
    const code = computed(() => state.room.code);

    const playUrl = computed(() => {
        if (!state.round?.id) {
            return '';
        }

        return `${window.location.origin}/play/${state.room.code}/${state.round.id}`;
    });

    const { copy: copyToClipboard, copied: copiedPlayUrl } = useClipboard({ legacy: true, copiedDuring: 1500 });

    const copyPlayUrl = async () => {
        if (!playUrl.value) {
            return;
        }

        try {
            await copyToClipboard(playUrl.value);
        } catch {
            // ignore
        }
    };

    const isRefreshing = ref(false);

    const refreshRequest = useAxios(undefined, undefined, window.axios, {
        immediate: false,
        abortPrevious: true,
    });

    const actionRequest = useAxios(undefined, undefined, window.axios, {
        immediate: false,
        abortPrevious: false,
    });

    const executeAction = async (url, config = {}) => {
        const result = await actionRequest.execute(url, config);

        if (result.error.value) {
            throw result.error.value;
        }

        return result;
    };

    const getRequestErrorMessage = (error, fallback) => {
        return error?.response?.data?.message
            ?? error?.response?.data?.error
            ?? fallback;
    };

    const refreshState = async () => {
        if (isRefreshing.value) {
            return;
        }

        if (!isOnline.value) {
            pendingRefresh.value = true;
            return;
        }

        isRefreshing.value = true;

        try {
            const result = await refreshRequest.execute(`/room/${code.value}/state`);
            const data = result.response.value?.data;

            if (data) {
                if (data.room?.state === 'reveal' && state.room.state !== 'reveal') {
                    capturePreviousLeaderboard();
                }

                applyState(data);
            }
        } finally {
            isRefreshing.value = false;
        }
    };

    const documentVisibility = useDocumentVisibility();
    const isOnline = useOnline();
    const pendingRefresh = ref(false);

    const scheduleRefresh = useDebounceFn(
        () => {
            if (!isOnline.value || documentVisibility.value !== 'visible') {
                pendingRefresh.value = true;
                return;
            }

            void refreshState();
        },
        100,
        { maxWait: 1000 },
    );

    watch(documentVisibility, (visibility) => {
        if (visibility !== 'visible' || !pendingRefresh.value) {
            return;
        }

        if (!isOnline.value) {
            return;
        }

        pendingRefresh.value = false;
        void refreshState();
    });

    watch(isOnline, (online) => {
        if (!online || !pendingRefresh.value) {
            return;
        }

        if (documentVisibility.value !== 'visible') {
            return;
        }

        pendingRefresh.value = false;
        void refreshState();
    });

    const {
        isRevealed: isFinishGameConfirmOpen,
        reveal: revealFinishGameConfirm,
        confirm: confirmFinishGameConfirm,
        cancel: cancelFinishGameConfirm,
    } = useConfirmDialog();

    const isFinishingGame = ref(false);
    const finishGameError = ref('');

    const openFinishGameConfirm = () => {
        if (!isHost.value || isFinished.value) {
            return;
        }

        if (isFinishGameConfirmOpen.value) {
            return;
        }

        finishGameError.value = '';

        if (!isOnline.value) {
            finishGameError.value = "You're offline.";
        }

        void revealFinishGameConfirm();
    };

    const selectProvider = async (providerId) => {
        if (!isHost.value || !isInLobby.value) {
            return;
        }

        if (!isOnline.value) {
            return;
        }

        try {
            await executeAction(`/room/${code.value}/provider`, {
                method: 'POST',
                data: {
                    playback_provider_id: providerId,
                },
            });

            await refreshState();
        } catch (e) {
            toast.error('Could not set provider', {
                description: getRequestErrorMessage(e, 'Please try again.'),
            });
        }
    };

    const startError = ref('');
    const isStarting = ref(false);

    const startGame = async () => {
        if (!isHost.value || !isInLobby.value) {
            return;
        }

        if (!isOnline.value) {
            startError.value = "You're offline.";
            return;
        }

        startError.value = '';
        isStarting.value = true;

        try {
            const result = await actionRequest.execute(`/room/${code.value}/start`, {
                method: 'POST',
            });

            if (result.error.value) {
                startError.value = result.error.value?.response?.data?.message
                    ?? 'Could not start the game. Please try again.';
                return;
            }

            await refreshState();

            toast.success('Game started');
        } finally {
            isStarting.value = false;
        }
    };

    const canStart = computed(() => isHost.value && isInLobby.value && Boolean(state.room.playback_provider) && !isStarting.value);

    const placeError = ref('');
    const isPlacing = ref(false);

    const place = async (insertedAfterTrackId) => {
        if (!isInRound.value) {
            return;
        }

        if (isPlacing.value) {
            return;
        }

        const wasPlaced = Boolean(state.me?.has_placed);

        placeError.value = '';
        isPlacing.value = true;

        if (!isOnline.value) {
            placeError.value = "You're offline.";
            isPlacing.value = false;
            return;
        }

        try {
            const result = await actionRequest.execute(`/room/${code.value}/place`, {
                method: 'POST',
                data: {
                    inserted_after_track_id: insertedAfterTrackId,
                },
            });

            if (result.error.value) {
                placeError.value = result.error.value?.response?.data?.message
                    ?? 'Could not submit placement. Please try again.';
                return;
            }

            await refreshState();

            if (!wasPlaced && state.me?.has_placed) {
                toast.success('Guess saved', {
                    description: 'You can change it until reveal.',
                });
            }
        } finally {
            isPlacing.value = false;
        }
    };

    const revealRound = async () => {
        if (!isHost.value || !isInRound.value) {
            return;
        }

        if (!isOnline.value) {
            return;
        }

        try {
            capturePreviousLeaderboard();

            await executeAction(`/room/${code.value}/reveal`, {
                method: 'POST',
            });
            await refreshState();

            toast.success('Revealed');
        } catch (e) {
            toast.error('Could not reveal', {
                description: getRequestErrorMessage(e, 'Please try again.'),
            });
        }
    };

    const nextRound = async () => {
        if (!isHost.value || !isInReveal.value) {
            return;
        }

        if (!isOnline.value) {
            return;
        }

        try {
            await executeAction(`/room/${code.value}/next`, {
                method: 'POST',
            });
            await refreshState();

            toast.success('Next round');
        } catch (e) {
            toast.error('Could not start next round', {
                description: getRequestErrorMessage(e, 'Please try again.'),
            });
        }
    };

    const finishGame = async () => {
        if (!isHost.value || isFinished.value || isFinishingGame.value) {
            return;
        }

        if (!isOnline.value) {
            finishGameError.value = "You're offline.";
            return;
        }

        isFinishingGame.value = true;
        finishGameError.value = '';

        try {
            const result = await actionRequest.execute(`/room/${code.value}/finish`, {
                method: 'POST',
            });

            if (result.error.value) {
                finishGameError.value = result.error.value?.response?.data?.message
                    ?? 'Could not finish the game. Please try again.';
                return;
            }

            confirmFinishGameConfirm();
            await refreshState();

            toast.success('Game closed');
        } finally {
            isFinishingGame.value = false;
        }
    };

    const cancelFinishGame = () => {
        finishGameError.value = '';
        cancelFinishGameConfirm();
    };

    // AMEN feature
    const isClaimingAmen = ref(false);
    const amenClaimError = ref('');
    const isSubmittingAmenGuess = ref(false);
    const amenGuessError = ref('');
    const isSubmittingAmenVote = ref(false);
    const amenVoteError = ref('');

    const claimAmen = async () => {
        if (!isInRound.value || !state.round?.id) {
            return;
        }

        if (isClaimingAmen.value) {
            return;
        }

        isClaimingAmen.value = true;
        amenClaimError.value = '';

        if (!isOnline.value) {
            amenClaimError.value = "You're offline.";
            isClaimingAmen.value = false;
            return;
        }

        try {
            const result = await actionRequest.execute(`/room/${code.value}/round/${state.round.id}/amen`, {
                method: 'POST',
            });

            if (result.error.value) {
                amenClaimError.value = result.error.value?.response?.data?.message ?? 'Could not claim AMEN.';
                return;
            }

            await refreshState();

            toast.success('AMEN claimed');
        } finally {
            isClaimingAmen.value = false;
        }
    };

    const submitAmenGuess = async (artist, title) => {
        if (!state.round?.id || isSubmittingAmenGuess.value) {
            return;
        }

        isSubmittingAmenGuess.value = true;
        amenGuessError.value = '';

        if (!isOnline.value) {
            amenGuessError.value = "You're offline.";
            isSubmittingAmenGuess.value = false;
            return;
        }

        try {
            const result = await actionRequest.execute(`/room/${code.value}/round/${state.round.id}/amen/guess`, {
                method: 'POST',
                data: {
                    artist,
                    title,
                },
            });

            if (result.error.value) {
                amenGuessError.value = result.error.value?.response?.data?.message ?? 'Could not submit guess.';
                return;
            }

            await refreshState();

            toast.success('AMEN guess saved');
        } finally {
            isSubmittingAmenGuess.value = false;
        }
    };

    const submitAmenVote = async (vote) => {
        if (!state.round?.id || isSubmittingAmenVote.value) {
            return;
        }

        isSubmittingAmenVote.value = true;
        amenVoteError.value = '';

        if (!isOnline.value) {
            amenVoteError.value = "You're offline.";
            isSubmittingAmenVote.value = false;
            return;
        }

        try {
            const result = await actionRequest.execute(`/room/${code.value}/round/${state.round.id}/amen/vote`, {
                method: 'POST',
                data: {
                    vote,
                },
            });

            if (result.error.value) {
                amenVoteError.value = result.error.value?.response?.data?.message ?? 'Could not submit vote.';
                return;
            }

            await refreshState();

            toast.success('AMEN vote saved');
        } finally {
            isSubmittingAmenVote.value = false;
        }
    };

    // Placement timer toggle
    const isTogglingPlacementTimer = ref(false);

    const togglePlacementTimer = async (enabled) => {
        if (!isHost.value || !isInLobby.value) {
            return;
        }

        if (!isOnline.value) {
            return;
        }

        isTogglingPlacementTimer.value = true;

        try {
            await executeAction(`/room/${code.value}/placement-timer`, {
                method: 'POST',
                data: {
                    enabled,
                },
            });

            await refreshState();
        } catch (e) {
            toast.error('Could not update timer setting', {
                description: getRequestErrorMessage(e, 'Please try again.'),
            });
        } finally {
            isTogglingPlacementTimer.value = false;
        }
    };

    // Ready feature
    const isMarkingReady = ref(false);
    const markReadyError = ref('');

    const markReady = async () => {
        if (!isInLobby.value) {
            return;
        }

        if (isMarkingReady.value) {
            return;
        }

        isMarkingReady.value = true;
        markReadyError.value = '';

        if (!isOnline.value) {
            markReadyError.value = "You're offline.";
            isMarkingReady.value = false;
            return;
        }

        try {
            const result = await actionRequest.execute(`/room/${code.value}/ready`, {
                method: 'POST',
            });

            if (result.error.value) {
                markReadyError.value = result.error.value?.response?.data?.message ?? 'Could not mark as ready.';
                return;
            }

            await refreshState();

            toast.success("You're ready");
        } finally {
            isMarkingReady.value = false;
        }
    };

    const previousLeaderboard = ref([]);

    // Game summary for finished games
    const summary = ref(null);
    const isLoadingSummary = ref(false);
    const summaryError = ref('');

    const fetchSummary = async () => {
        if (!isFinished.value || summary.value) {
            return;
        }

        if (!isOnline.value) {
            summaryError.value = "You're offline.";
            return;
        }

        isLoadingSummary.value = true;
        summaryError.value = '';

        try {
            const result = await refreshRequest.execute(`/room/${code.value}/summary`);
            const data = result.response.value?.data;

            if (data) {
                summary.value = data;
            }
        } catch (e) {
            summaryError.value = getRequestErrorMessage(e, 'Could not load summary.');
        } finally {
            isLoadingSummary.value = false;
        }
    };

    // Auto-fetch summary when game finishes
    watch(
        isFinished,
        (finished) => {
            if (finished && !summary.value) {
                void fetchSummary();
            }
        },
        { immediate: true },
    );

    const leaderboard = computed(() => {
        const players = [...state.players];
        players.sort((a, b) => {
            if (b.score !== a.score) {
                return b.score - a.score;
            }

            return a.id - b.id;
        });

        let rank = 0;
        let lastScore = null;
        let sameRankCount = 0;

        return players.map((p) => {
            if (lastScore !== p.score) {
                rank += 1 + sameRankCount;
                sameRankCount = 0;
            } else {
                sameRankCount += 1;
            }

            lastScore = p.score;

            return {
                ...p,
                rank,
            };
        });
    });

    const capturePreviousLeaderboard = () => {
        previousLeaderboard.value = [...leaderboard.value];
    };

    const cleanup = () => {
        scheduleRefresh.cancel?.();
        cancelFinishGame();
    };

    return {
        state,

        code,
        isHost,
        isInLobby,
        isInRound,
        isInReveal,
        isFinished,

        isRefreshing,
        refreshState,
        scheduleRefresh,
        cleanup,

        playUrl,
        copyPlayUrl,
        copiedPlayUrl,

        selectProvider,
        startGame,
        canStart,
        startError,
        isStarting,

        place,
        placeError,
        isPlacing,
        revealRound,
        nextRound,
        finishGame,
        isFinishGameConfirmOpen,
        openFinishGameConfirm,
        isFinishingGame,
        finishGameError,
        cancelFinishGame,

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

        leaderboard,
        previousLeaderboard,

        // Summary
        summary,
        isLoadingSummary,
        summaryError,
        fetchSummary,
    };
}
