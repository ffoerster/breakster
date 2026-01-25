import { computed, ref, watch } from 'vue';

export function useSpotifyHostPlayback({ state, isHost, code, refreshState }) {
    const spotifyDevices = ref([]);
    const spotifyError = ref('');
    const isLoadingDevices = ref(false);
    const spotifyPlayError = ref('');
    const isPlayingSpotify = ref(false);

    const hasSpotifyToken = computed(() => Boolean(state.room.host_has_spotify_token));
    const isSpotifyProvider = computed(() => state.room.playback_provider?.key === 'spotify');
    const selectedDeviceId = computed(() => state.room.spotify_device_id);

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
                spotifyError.value =
                    'No devices found. Open Spotify on your phone or computer and start playing something once, then refresh.';
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

    const playSpotifyTrack = async () => {
        if (!isHost.value || !isSpotifyProvider.value) {
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

    watch(
        () => state.room.playback_provider?.id,
        async () => {
            if (isHost.value && isSpotifyProvider.value) {
                await loadSpotifyDevices();
            }
        },
    );

    return {
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
    };
}
