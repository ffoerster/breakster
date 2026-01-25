import { computed, reactive, ref } from 'vue';

export function useRoomState(props) {
    const state = reactive({
        room: props.room,
        me: props.me,
        players: props.players,
        providers: props.providers,
        round: props.round,
        timeline: props.timeline,
    });

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

    const copyPlayUrl = async () => {
        if (!playUrl.value) {
            return;
        }

        try {
            await navigator.clipboard.writeText(playUrl.value);
        } catch {
            // ignore
        }
    };

    const isRefreshing = ref(false);
    let refreshTimeoutId = null;

    const refreshState = async () => {
        if (isRefreshing.value) {
            return;
        }

        isRefreshing.value = true;

        try {
            const res = await window.axios.get(`/room/${code.value}/state`);
            Object.assign(state, res.data);
        } finally {
            isRefreshing.value = false;
        }
    };

    const scheduleRefresh = () => {
        if (refreshTimeoutId) {
            clearTimeout(refreshTimeoutId);
        }

        refreshTimeoutId = setTimeout(() => {
            refreshState();
        }, 100);
    };

    const selectProvider = async (providerId) => {
        if (!isHost.value || !isInLobby.value) {
            return;
        }

        await window.axios.post(`/room/${code.value}/provider`, {
            playback_provider_id: providerId,
        });

        await refreshState();
    };

    const startError = ref('');
    const isStarting = ref(false);

    const startGame = async () => {
        if (!isHost.value || !isInLobby.value) {
            return;
        }

        startError.value = '';
        isStarting.value = true;

        try {
            await window.axios.post(`/room/${code.value}/start`);
            await refreshState();
        } catch (e) {
            startError.value = e?.response?.data?.message ?? 'Could not start the game. Please try again.';
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

        placeError.value = '';
        isPlacing.value = true;

        try {
            await window.axios.post(`/room/${code.value}/place`, {
                inserted_after_track_id: insertedAfterTrackId,
            });
            await refreshState();
        } catch (e) {
            placeError.value = e?.response?.data?.message ?? 'Could not submit placement. Please try again.';
        } finally {
            isPlacing.value = false;
        }
    };

    const revealRound = async () => {
        if (!isHost.value || !isInRound.value) {
            return;
        }

        await window.axios.post(`/room/${code.value}/reveal`);
        await refreshState();
    };

    const nextRound = async () => {
        if (!isHost.value || !isInReveal.value) {
            return;
        }

        await window.axios.post(`/room/${code.value}/next`);
        await refreshState();
    };

    const finishGame = async () => {
        if (!isHost.value || isFinished.value) {
            return;
        }

        const ok = window.confirm('Finish this game? Players will no longer be able to join.');
        if (!ok) {
            return;
        }

        await window.axios.post(`/room/${code.value}/finish`);
        await refreshState();
    };

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

    const cleanup = () => {
        if (refreshTimeoutId) {
            clearTimeout(refreshTimeoutId);
        }
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

        leaderboard,
    };
}
