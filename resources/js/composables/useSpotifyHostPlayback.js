import { useAxios } from '@vueuse/integrations/useAxios';
import { useOnline } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

export function useSpotifyHostPlayback({ state, isHost, code, refreshState }) {
    const spotifyDevices = ref([]);
    const spotifyError = ref('');
    const isLoadingDevices = ref(false);
    const spotifyPlayError = ref('');
    const isPlayingSpotify = ref(false);

    const isOnline = useOnline();

    const spotifyLoadRequest = useAxios(undefined, undefined, window.axios, {
        immediate: false,
        abortPrevious: true,
    });

    const spotifyActionRequest = useAxios(undefined, undefined, window.axios, {
        immediate: false,
        abortPrevious: false,
    });

    const hasSpotifyToken = computed(() => Boolean(state.room.host_has_spotify_token));
    const isSpotifyProvider = computed(() => state.room.playback_provider?.key === 'spotify');
    const selectedDeviceId = computed(() => state.room.spotify_device_id);

    const loadSpotifyDevices = async () => {
        if (!isHost.value || !isSpotifyProvider.value) {
            return;
        }

        spotifyError.value = '';
        isLoadingDevices.value = true;

        if (!isOnline.value) {
            spotifyError.value = "You're offline.";
            isLoadingDevices.value = false;
            return;
        }

        try {
            const result = await spotifyLoadRequest.execute(`/room/${code.value}/spotify/devices`);

            if (result.error.value) {
                const status = result.error.value?.response?.status;
                const message = result.error.value?.response?.data?.error;

                if (status === 403 && message?.includes('Premium')) {
                    spotifyError.value = 'Spotify Premium required for host-controlled playback.';
                } else if (message) {
                    spotifyError.value = message;
                } else {
                    spotifyError.value = 'Failed to load Spotify devices. Please try again.';
                }

                spotifyDevices.value = [];
                return;
            }

            spotifyDevices.value = result.response.value?.data?.devices || [];

            if (spotifyDevices.value.length === 0) {
                spotifyError.value =
                    'No devices found. Open Spotify on your phone or computer and start playing something once, then refresh.';
            }
        } finally {
            isLoadingDevices.value = false;
        }
    };

    const selectSpotifyDevice = async (deviceId) => {
        if (!isHost.value || !isSpotifyProvider.value) {
            return;
        }

        spotifyError.value = '';

        if (!isOnline.value) {
            spotifyError.value = "You're offline.";
            return;
        }

        try {
            const result = await spotifyActionRequest.execute(`/room/${code.value}/spotify/device`, {
                method: 'POST',
                data: {
                    device_id: deviceId,
                },
            });

            if (result.error.value) {
                spotifyError.value = result.error.value?.response?.data?.error || 'Failed to set device. Please try again.';
                return;
            }

            await refreshState();
        } finally {
            // noop
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

        if (!isOnline.value) {
            spotifyPlayError.value = "You're offline.";
            isPlayingSpotify.value = false;
            return;
        }

        try {
            const result = await spotifyActionRequest.execute(`/room/${code.value}/spotify/play`, {
                method: 'POST',
            });

            if (result.error.value) {
                spotifyPlayError.value = result.error.value?.response?.data?.error
                    || 'Failed to start playback. Please try again.';
            }
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
