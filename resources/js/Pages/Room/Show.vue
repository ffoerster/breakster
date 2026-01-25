<script setup>
import { Link } from '@inertiajs/vue3';
import QrcodeVue from 'qrcode.vue';
import Sortable from 'sortablejs';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

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

// Spotify device management
const spotifyDevices = ref([]);
const spotifyError = ref('');
const isLoadingDevices = ref(false);
const hasSpotifyToken = computed(() => Boolean(state.room.host_has_spotify_token));
const isSpotifyProvider = computed(() => state.room.playback_provider?.key === 'spotify');
const selectedDeviceId = computed(() => state.room.spotify_device_id);
const isGuestHost = computed(() => isHost.value && !state.room.host_user_id);

const loadSpotifyDevices = async () => {
    if (!isHost.value || !isSpotifyProvider.value) {
        return;
    }

    spotifyError.value = '';
    isLoadingDevices.value = true;

    try {
        const res = await window.axios.get(`/room/${code.value}/spotify/devices`);
        spotifyDevices.value = res.data.devices || [];

        if (spotifyDevices.value.length === 0) {
            spotifyError.value = 'No devices found. Open Spotify on your phone or computer and start playing something once, then refresh.';
        }
    } catch (e) {
        const status = e?.response?.status;
        const message = e?.response?.data?.error;

        if (status === 403 && message?.includes('Premium')) {
            spotifyError.value = 'Spotify Premium required for host-controlled playback.';
        } else if (message) {
            spotifyError.value = message;
        } else {
            spotifyError.value = 'Failed to load Spotify devices. Please try again.';
        }

        spotifyDevices.value = [];
    } finally {
        isLoadingDevices.value = false;
    }
};

const selectSpotifyDevice = async (deviceId) => {
    if (!isHost.value || !isSpotifyProvider.value) {
        return;
    }

    spotifyError.value = '';

    try {
        await window.axios.post(`/room/${code.value}/spotify/device`, {
            device_id: deviceId,
        });

        await refreshState();
    } catch (e) {
        spotifyError.value = e?.response?.data?.error || 'Failed to set device. Please try again.';
    }
};

const connectSpotify = () => {
    const redirect = encodeURIComponent(window.location.href);
    window.location.href = `/auth/spotify/connect?redirect=${redirect}`;
};

// Watch for provider changes to load devices
watch(
    () => state.room.playback_provider?.id,
    async (newProviderId) => {
        if (isHost.value && state.room.playback_provider?.key === 'spotify') {
            await loadSpotifyDevices();
        }
    },
);

const startGame = async () => {
    if (!isHost.value || !isInLobby.value) {
        return;
    }

    await window.axios.post(`/room/${code.value}/start`);
    await refreshState();
};

const canStart = computed(() => isHost.value && isInLobby.value && Boolean(state.room.playback_provider));

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

const placeError = ref('');
const isPlacing = ref(false);

const place = async (insertedAfterTrackId) => {
    if (!isInRound.value || isPlacementLocked.value || state.me?.has_placed) {
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

const spotifyPlayError = ref('');
const isPlayingSpotify = ref(false);

const playSpotifyTrack = async () => {
    if (!isHost.value || !isInRound.value || !isSpotifyProvider.value) {
        return;
    }

    spotifyPlayError.value = '';
    isPlayingSpotify.value = true;

    try {
        await window.axios.post(`/room/${code.value}/spotify/play`);
    } catch (e) {
        spotifyPlayError.value = e?.response?.data?.error || 'Failed to start playback. Please try again.';
    } finally {
        isPlayingSpotify.value = false;
    }
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

let channel = null;

const mysterySourceEl = ref(null);
const placementListEl = ref(null);
let mysterySortable = null;
let placementSortable = null;

const shouldEnablePlacementDnd = computed(() => {
    return Boolean(isInRound.value && !isPlacementLocked.value && !state.me?.has_placed);
});

const destroyPlacementDnD = () => {
    if (mysterySortable) {
        mysterySortable.destroy();
        mysterySortable = null;
    }

    if (placementSortable) {
        placementSortable.destroy();
        placementSortable = null;
    }
};

const initPlacementDnD = () => {
    if (!mysterySourceEl.value || !placementListEl.value) {
        return;
    }

    destroyPlacementDnD();

    mysterySortable = Sortable.create(mysterySourceEl.value, {
        group: {
            name: 'placement',
            pull: 'clone',
            put: false,
        },
        sort: false,
        animation: 150,
        ghostClass: 'opacity-60',
        dragClass: 'opacity-90',
        delay: 120,
        delayOnTouchOnly: true,
        touchStartThreshold: 5,
        fallbackOnBody: true,
    });

    placementSortable = Sortable.create(placementListEl.value, {
        group: {
            name: 'placement',
            pull: false,
            put: true,
        },
        sort: false,
        animation: 150,
        ghostClass: 'opacity-60',
        fallbackOnBody: true,
        swapThreshold: 0.65,
        onAdd: async (evt) => {
            const newIndex = evt.newIndex;
            const timeline = state.timeline ?? [];

            let insertedAfterTrackId = null;
            if (typeof newIndex === 'number' && newIndex > 0) {
                insertedAfterTrackId = timeline[newIndex - 1]?.track_id ?? null;
            }

            evt.item.remove();

            await place(insertedAfterTrackId);
        },
    });
};

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

watch(
    () => state.room.state,
    async () => {
        await nextTick();

        if (!state.me) {
            return;
        }

        if (shouldEnablePlacementDnd.value) {
            initPlacementDnD();
        } else {
            destroyPlacementDnD();
        }
    },
    { immediate: true },
);

watch(
    () => shouldEnablePlacementDnd.value,
    async (enabled) => {
        await nextTick();

        if (!state.me) {
            return;
        }

        if (enabled) {
            initPlacementDnD();
        } else {
            destroyPlacementDnD();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (refreshTimeoutId) {
        clearTimeout(refreshTimeoutId);
    }

    if (countdownIntervalId) {
        clearInterval(countdownIntervalId);
    }

    destroyPlacementDnD();

    if (window.Echo) {
        window.Echo.leave(`room.${code.value}`);
    }
});
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <header class="py-4 px-6 flex items-center justify-between">
            <Link href="/" class="text-2xl font-bold text-purple-400 hover:text-purple-300 transition">
                Breakster
            </Link>
            <div class="text-right">
                <div class="text-xs text-gray-400">Room code</div>
                <div class="font-mono font-bold text-purple-300 tracking-widest">{{ state.room.code }}</div>
            </div>
        </header>

        <main class="flex-1 flex items-center justify-center px-4 py-8">
            <div class="w-full max-w-4xl">
                <div v-if="!state.me" class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8 text-center">
                    <h2 class="text-2xl font-bold mb-4">You're not in this room</h2>
                    <Link
                        :href="`/room/join?code=${state.room.code}`"
                        class="inline-block px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-bold transition"
                    >
                        Join Room {{ state.room.code }}
                    </Link>
                </div>

                <div v-else class="space-y-6">
                    <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                            <div>
                                <h1 class="text-3xl font-bold">Room {{ state.room.code }}</h1>
                                <div class="mt-3">
                                    <div class="text-sm text-gray-500 uppercase tracking-wide">Music Pack</div>
                                    <p class="text-xl font-semibold text-purple-400">{{ state.room.pack.name }}</p>
                                    <p v-if="state.room.pack.description" class="text-sm text-gray-400 mt-1">
                                        {{ state.room.pack.description }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-left md:text-right">
                                <div class="text-sm text-gray-400">You are</div>
                                <div class="text-lg font-bold">
                                    {{ state.me.display_name }}
                                    <span v-if="state.me.is_host" class="ml-2 px-2 py-1 bg-yellow-500/20 text-yellow-400 text-xs rounded-full">
                                        Host
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="isInLobby" class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                        <h2 class="text-xl font-bold mb-4">Listening App</h2>
                        <p class="text-gray-400 text-sm mb-4">Host chooses how players open tracks during the game.</p>

                        <div v-if="state.room.playback_provider" class="flex items-center gap-2 text-green-400 mb-4">
                            <span class="font-medium">Listening via {{ state.room.playback_provider.name }}</span>
                        </div>
                        <div v-else class="flex items-center gap-2 text-yellow-400 mb-4">
                            <span class="font-medium">Waiting for host to choose listening app…</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <button
                                v-for="provider in state.providers"
                                :key="provider.id"
                                type="button"
                                class="relative p-4 rounded-lg border-2 transition-all"
                                :disabled="!isHost || Boolean(state.room.playback_provider?.id === provider.id)"
                                :class="
                                    state.room.playback_provider?.id === provider.id
                                        ? 'border-purple-500 bg-purple-500/20'
                                        : isHost
                                            ? 'border-gray-600 bg-gray-700/30 hover:border-gray-500 hover:bg-gray-700/50'
                                            : 'border-gray-700 bg-gray-800/30 opacity-60 cursor-not-allowed'
                                "
                                @click="selectProvider(provider.id)"
                            >
                                <div class="text-lg font-bold" :class="state.room.playback_provider?.id === provider.id ? 'text-purple-400' : 'text-white'">
                                    {{ provider.name }}
                                </div>
                                <div class="text-sm text-gray-400 mt-1">Players open tracks in {{ provider.name }}</div>
                            </button>
                        </div>

                        <!-- Spotify Device Selection (Host Only) -->
                        <div v-if="isHost && isSpotifyProvider" class="mt-6 p-4 bg-gray-700/30 rounded-lg border border-gray-600">
                            <h3 class="text-lg font-bold mb-3 flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
                                </svg>
                                Spotify Host Playback
                            </h3>
                            <p class="text-sm text-gray-400 mb-4">
                                Host-controlled playback lets you start tracks automatically on your device during the game.
                            </p>

                            <!-- Not Connected -->
                            <div v-if="!hasSpotifyToken" class="space-y-3">
                                <div class="p-3 bg-yellow-500/20 border border-yellow-500 rounded-lg text-sm">
                                    <div class="font-bold text-yellow-400 mb-1">Not Connected</div>
                                    <div class="text-gray-300">Connect your Spotify account to enable host playback control.</div>
                                </div>
                                <button
                                    type="button"
                                    class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-bold transition flex items-center justify-center gap-2"
                                    @click="connectSpotify"
                                >
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
                                    </svg>
                                    Connect Spotify
                                </button>
                            </div>

                            <!-- Connected - Device Selection -->
                            <div v-else class="space-y-3">
                                <div class="p-3 bg-green-500/20 border border-green-500 rounded-lg text-sm">
                                    <div class="font-bold text-green-400 mb-1">✓ Spotify Connected</div>
                                    <div class="text-gray-300">Select a device to play tracks on during the game.</div>
                                </div>

                                <!-- Error Message -->
                                <div v-if="spotifyError" class="p-3 bg-red-500/20 border border-red-500 rounded-lg text-sm">
                                    <div class="font-bold text-red-400 mb-1">Error</div>
                                    <div class="text-gray-300">{{ spotifyError }}</div>
                                </div>

                                <!-- Device Selector -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-medium">Select Device:</label>
                                        <button
                                            type="button"
                                            class="text-sm text-purple-400 hover:text-purple-300"
                                            :disabled="isLoadingDevices"
                                            @click="loadSpotifyDevices"
                                        >
                                            {{ isLoadingDevices ? 'Loading...' : 'Refresh' }}
                                        </button>
                                    </div>

                                    <div v-if="spotifyDevices.length > 0" class="space-y-2">
                                        <button
                                            v-for="device in spotifyDevices"
                                            :key="device.id"
                                            type="button"
                                            class="w-full p-3 rounded-lg border-2 transition text-left"
                                            :class="
                                                selectedDeviceId === device.id
                                                    ? 'border-green-500 bg-green-500/20'
                                                    : 'border-gray-600 bg-gray-700/30 hover:border-gray-500'
                                            "
                                            @click="selectSpotifyDevice(device.id)"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="font-bold">{{ device.name }}</div>
                                                    <div class="text-xs text-gray-400">{{ device.type }}</div>
                                                </div>
                                                <div v-if="device.is_active" class="text-xs text-green-400 font-bold">
                                                    Active
                                                </div>
                                                <div v-else-if="selectedDeviceId === device.id" class="text-xs text-green-400 font-bold">
                                                    Selected ✓
                                                </div>
                                            </div>
                                        </button>
                                    </div>

                                    <div v-else-if="!isLoadingDevices" class="p-4 bg-gray-700/30 rounded-lg text-center text-sm text-gray-400">
                                        <div class="mb-2">No devices found</div>
                                        <div class="text-xs">
                                            Open Spotify on your phone or computer and start playing something once, then click Refresh.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex gap-4">
                            <button
                                v-if="isHost"
                                type="button"
                                class="flex-1 py-4 px-6 font-bold rounded-lg transition"
                                :class="canStart ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-gray-600 text-gray-400 cursor-not-allowed'"
                                :disabled="!canStart"
                                @click="startGame"
                            >
                                Start Game
                            </button>
                            <div
                                v-else
                                class="flex-1 py-4 px-6 bg-gray-700 text-gray-400 font-bold rounded-lg text-center"
                            >
                                Waiting for host to start…
                            </div>
                        </div>
                    </div>

                    <div v-if="isInLobby" class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                        <h2 class="text-xl font-bold mb-4">Players ({{ state.players.length }})</h2>
                        <div class="grid gap-3">
                            <div
                                v-for="p in state.players"
                                :key="p.id"
                                class="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center font-bold">
                                        {{ String(p.display_name).slice(0, 1).toUpperCase() }}
                                    </div>
                                    <div class="font-medium">
                                        {{ p.display_name }}
                                        <span v-if="p.is_me" class="text-purple-400">(You)</span>
                                    </div>
                                </div>
                                <span v-if="p.is_host" class="px-3 py-1 bg-yellow-500/20 text-yellow-400 text-sm rounded-full">
                                    Host
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="isInRound" class="space-y-6">
                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8 text-center">
                            <div class="text-sm text-gray-400 mb-2">Round {{ state.round.round_number }}</div>
                            <h2 class="text-2xl font-bold mb-6">Listen and place this track!</h2>

                            <div v-if="isHost" class="max-w-sm mx-auto mb-6">
                                <!-- Spotify Host Playback Control -->
                                <div v-if="isSpotifyProvider && state.room.spotify_device_id" class="mb-6">
                                    <button
                                        type="button"
                                        class="w-full px-6 py-4 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg font-bold transition flex items-center justify-center gap-3"
                                        :disabled="isPlayingSpotify"
                                        @click="playSpotifyTrack"
                                    >
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
                                        </svg>
                                        <span v-if="isPlayingSpotify">Playing...</span>
                                        <span v-else>Play on Spotify</span>
                                    </button>
                                    <div v-if="spotifyPlayError" class="mt-2 text-sm text-red-400 text-center">
                                        {{ spotifyPlayError }}
                                    </div>
                                    <div class="mt-2 text-xs text-gray-400 text-center">
                                        Plays on your selected Spotify device
                                    </div>
                                </div>

                                <div class="text-sm text-gray-400 mb-3">Scan to open in {{ state.room.playback_provider?.name ?? 'your app' }}</div>

                                <QrcodeVue
                                    v-if="playUrl"
                                    :value="playUrl"
                                    :size="220"
                                    level="M"
                                    class="mx-auto bg-white p-3 rounded-xl"
                                />

                                <div class="mt-3 text-xs text-gray-400 break-all font-mono">{{ playUrl }}</div>

                                <button
                                    type="button"
                                    class="mt-3 px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg font-bold transition"
                                    @click="copyPlayUrl"
                                >
                                    Copy link
                                </button>
                            </div>

                            <div class="max-w-sm mx-auto">
                                <audio v-if="state.round.playback?.preview_url" controls class="w-full" autoplay>
                                    <source :src="state.round.playback.preview_url" type="audio/mpeg" />
                                </audio>

                                <a
                                    v-if="state.round.playback?.external_url"
                                    :href="state.round.playback.external_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center justify-center gap-2 px-6 py-4 bg-blue-600 hover:bg-blue-700 rounded-lg font-bold transition mt-4"
                                >
                                    Open in {{ state.round.playback.provider_name }}
                                </a>
                            </div>

                            <p class="mt-4 text-gray-400 text-sm">No titles, no artists, no years — just your ears.</p>
                        </div>

                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-6">
                            <h3 class="text-lg font-bold mb-4 text-center">Current Track</h3>
                            <div
                                ref="mysterySourceEl"
                                class="select-none"
                                :class="isPlacementLocked || state.me.has_placed ? 'opacity-50' : ''"
                            >
                                <div
                                    class="flex items-center gap-3 p-4 bg-linear-to-r from-purple-600/30 to-pink-600/30 border-2 border-purple-500/50 rounded-lg"
                                    :class="
                                        isPlacementLocked || state.me.has_placed
                                            ? 'cursor-not-allowed'
                                            : 'cursor-grab active:cursor-grabbing'
                                    "
                                >
                                    <div class="w-16 h-16 rounded bg-purple-900/60 flex items-center justify-center shrink-0">
                                        <svg class="w-8 h-8 text-purple-200" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                d="M10 2a5 5 0 00-5 5v2a2 2 0 00-2 2v5a2 2 0 002 2h10a2 2 0 002-2v-5a2 2 0 00-2-2H7V7a3 3 0 015.905-.75 1 1 0 001.937-.5A5.002 5.002 0 0010 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0 text-center">
                                        <div class="text-2xl font-black text-purple-300">???</div>
                                        <div class="text-sm text-gray-300 font-medium">Mystery Track</div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <span v-if="state.me.has_placed">Placed</span>
                                            <span v-else-if="isPlacementLocked">Locked</span>
                                            <span v-else>Drag into your timeline</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                            <h3 class="text-xl font-bold mb-4">Your Timeline</h3>

                            <div
                                v-if="isPlacementLocked"
                                class="p-4 bg-blue-500/20 border border-blue-500 rounded-lg text-center mb-4"
                            >
                                Placement unlocks in {{ remainingSeconds }}s…
                            </div>

                            <div
                                v-if="state.me.has_placed"
                                class="p-4 bg-green-500/20 border border-green-500 rounded-lg text-center mb-4"
                            >
                                You've placed your guess! Waiting for other players…
                            </div>

                            <div v-if="placeError" class="p-4 bg-red-500/20 border border-red-500 rounded-lg text-center mb-4">
                                {{ placeError }}
                            </div>

                            <div class="text-sm text-gray-400 mb-3">
                                <span v-if="!state.me.has_placed">Drop the mystery card between years.</span>
                                <span v-else>Timeline updates after reveal.</span>
                            </div>

                            <ul
                                ref="placementListEl"
                                class="space-y-2 min-h-[4rem] p-3 rounded-lg border-2 border-dashed"
                                :class="
                                    shouldEnablePlacementDnd
                                        ? 'border-purple-500/40 bg-gray-900/20'
                                        : 'border-gray-700/60 bg-gray-900/10'
                                "
                            >
                                <li
                                    v-for="item in (state.timeline ?? [])"
                                    :key="item.track_id"
                                    class="flex items-center gap-3 p-4 bg-gray-700/30 rounded-lg"
                                >
                                    <img
                                        v-if="item.artwork_url"
                                        :src="item.artwork_url"
                                        alt=""
                                        class="w-16 h-16 rounded object-cover shrink-0"
                                    />
                                    <div v-else class="w-16 h-16 rounded bg-gray-600 shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <div class="text-2xl md:text-3xl font-black text-purple-400 leading-none mb-1">
                                            {{ item.year }}
                                        </div>
                                        <div class="font-medium truncate">{{ item.title }}</div>
                                        <div class="text-sm text-gray-400 truncate">{{ item.artist }}</div>
                                    </div>
                                </li>
                            </ul>

                            <div v-if="(state.timeline?.length ?? 0) === 0" class="text-center text-gray-500 mt-3">
                                Drop into the empty box to place as your first track
                            </div>
                        </div>

                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="text-sm text-gray-400">
                                    {{ state.round.placements_count }} / {{ state.round.players_count }} players have placed
                                </div>
                                <button
                                    v-if="isHost"
                                    type="button"
                                    class="px-6 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="state.round.players_count === 0"
                                    @click="revealRound"
                                >
                                    Reveal Answers
                                </button>
                                <div v-else class="text-sm text-gray-400">Waiting for host to reveal…</div>
                            </div>
                        </div>

                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                            <h2 class="text-xl font-bold mb-4">Players</h2>
                            <div class="grid gap-3">
                                <div
                                    v-for="p in state.players"
                                    :key="p.id"
                                    class="flex items-center justify-between p-4 bg-gray-700/30 rounded-lg"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center font-bold">
                                            {{ String(p.display_name).slice(0, 1).toUpperCase() }}
                                        </div>
                                        <div class="font-medium">
                                            {{ p.display_name }}
                                            <span v-if="p.is_me" class="text-purple-400">(You)</span>
                                        </div>
                                    </div>
                                    <div class="font-bold text-purple-300">{{ p.score }} pts</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="isInReveal" class="space-y-6">
                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8 text-center">
                            <div class="text-sm text-gray-400 mb-2">Round {{ state.round.round_number }} - Revealed!</div>

                            <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                                <img
                                    v-if="state.round.track?.artwork_url"
                                    :src="state.round.track.artwork_url"
                                    alt=""
                                    class="w-32 h-32 rounded-lg shadow-lg"
                                />
                                <div class="text-left">
                                    <div class="text-5xl font-black text-purple-400 leading-none mb-3">
                                        {{ state.round.track?.year }}
                                    </div>
                                    <h2 class="text-2xl font-bold">{{ state.round.track?.title }}</h2>
                                    <p class="text-lg text-gray-300">{{ state.round.track?.artist }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                            <h3 class="text-xl font-bold mb-4">Results</h3>
                            <div class="space-y-3">
                                <div
                                    v-for="placement in (state.round.placements ?? [])"
                                    :key="placement.id"
                                    class="flex items-center justify-between p-4 rounded-lg border"
                                    :class="placement.is_correct ? 'bg-green-500/20 border-green-500' : 'bg-red-500/20 border-red-500'"
                                >
                                    <div>
                                        <div class="font-medium">{{ placement.player.display_name }}</div>
                                        <div class="text-sm text-gray-400">
                                            <span v-if="placement.inserted_after_track_id === null">Placed at beginning</span>
                                            <span v-else>Placed after {{ placement.inserted_after_year ?? 'unknown' }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div v-if="placement.is_correct" class="text-green-400 font-bold">
                                            +{{ placement.points_earned }} pts
                                        </div>
                                        <div v-else class="text-red-400">0 pts</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                            <h3 class="text-xl font-bold mb-4">Leaderboard</h3>
                            <div class="space-y-2">
                                <div
                                    v-for="p in leaderboard"
                                    :key="p.id"
                                    class="flex items-center justify-between p-3 rounded-lg"
                                    :class="p.is_me ? 'bg-purple-500/20' : 'bg-gray-700/30'"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center font-bold text-sm">
                                            {{ p.rank }}
                                        </div>
                                        <span>{{ p.display_name }}</span>
                                    </div>
                                    <span class="font-bold text-purple-400">{{ p.score }} pts</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="isHost" class="flex justify-center">
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button
                                    type="button"
                                    class="px-8 py-4 bg-green-600 hover:bg-green-700 rounded-lg font-bold text-lg transition"
                                    @click="nextRound"
                                >
                                    Next Round
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-400">Waiting for host to start the next round…</div>
                    </div>

                    <div v-if="isFinished" class="space-y-6">
                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8 text-center">
                            <h1 class="text-4xl font-bold mb-6">Game Over!</h1>

                            <div v-if="leaderboard[0]" class="inline-block p-6 bg-yellow-500/20 rounded-2xl border border-yellow-500 mb-4">
                                <div class="text-yellow-400 text-sm mb-2">Winner</div>
                                <div class="text-3xl font-bold">{{ leaderboard[0].display_name }}</div>
                                <div class="text-2xl text-yellow-400">{{ leaderboard[0].score }} points</div>
                            </div>
                        </div>

                        <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
                            <h2 class="text-xl font-bold mb-4 text-center">Final Standings</h2>
                            <div class="max-w-md mx-auto space-y-2">
                                <div
                                    v-for="p in leaderboard"
                                    :key="p.id"
                                    class="flex items-center justify-between p-4 rounded-lg"
                                    :class="
                                        p.rank === 1
                                            ? 'bg-yellow-500/20 border border-yellow-500'
                                            : p.is_me
                                                ? 'bg-purple-500/20'
                                                : 'bg-gray-700/30'
                                    "
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 rounded-full flex items-center justify-center font-bold"
                                            :class="p.rank === 1 ? 'bg-yellow-500 text-black' : 'bg-gray-600'"
                                        >
                                            {{ p.rank }}
                                        </div>
                                        <span class="font-medium">
                                            {{ p.display_name }}
                                            <span v-if="p.is_me" class="text-gray-400">(You)</span>
                                        </span>
                                    </div>
                                    <span class="font-bold text-purple-400">{{ p.score }} pts</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center gap-4">
                            <Link href="/room/create" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-bold transition">
                                Play Again
                            </Link>
                            <Link href="/" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-bold transition">
                                Home
                            </Link>
                        </div>
                    </div>

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
            </div>
        </main>

        <footer class="py-4 text-center text-gray-500 text-sm">A timeline music game</footer>
    </div>
</template>
