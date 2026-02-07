<script setup>
defineProps({
    hasSpotifyToken: {
        type: Boolean,
        required: true,
    },
    spotifyDevices: {
        type: Array,
        required: true,
    },
    spotifyError: {
        type: String,
        required: true,
    },
    isLoadingDevices: {
        type: Boolean,
        required: true,
    },
    selectedDeviceId: {
        type: [String, null],
        default: null,
    },
    connectSpotify: {
        type: Function,
        required: true,
    },
    loadSpotifyDevices: {
        type: Function,
        required: true,
    },
    selectSpotifyDevice: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <div class="mt-6 p-4 bg-white/5 rounded-lg border border-white/10">
        <h3 class="text-lg font-bold mb-3 flex items-center gap-2 font-display">
            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                <path
                    d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"
                />
            </svg>
            Spotify Host Playback
        </h3>

        <p class="text-sm text-white/60 mb-4">
            Host-controlled playback lets you start tracks automatically on your device during the game.
        </p>

        <div v-if="!hasSpotifyToken" class="space-y-3">
                <div class="p-3 bg-yellow-500/20 border border-yellow-500 rounded-lg text-sm">
                    <div class="font-bold text-yellow-400 mb-1">Not Connected</div>
                    <div class="text-white/75">Connect your Spotify account to enable host playback control.</div>
                </div>
            <button
                type="button"
                class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-bold transition flex items-center justify-center gap-2"
                @click="connectSpotify"
            >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.540.780.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"
                    />
                </svg>
                Connect Spotify
            </button>
        </div>

        <div v-else class="space-y-3">
            <div class="p-3 bg-green-500/20 border border-green-500 rounded-lg text-sm">
                <div class="font-bold text-green-400 mb-1">✓ Spotify Connected</div>
                <div class="text-white/75">Select a device to play tracks on during the game.</div>
            </div>

            <div v-if="spotifyError" class="p-3 bg-red-500/20 border border-red-500 rounded-lg text-sm">
                <div class="font-bold text-red-400 mb-1">Error</div>
                <div class="text-white/75">{{ spotifyError }}</div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium">Select Device:</label>
                    <button
                        type="button"
                        class="text-sm text-accent-400 hover:text-accent-300"
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
                                : 'border-white/10 bg-white/5 hover:border-white/20'
                        "
                        @click="selectSpotifyDevice(device.id)"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-bold">{{ device.name }}</div>
                                <div class="text-xs text-white/60">{{ device.type }}</div>
                            </div>
                            <div v-if="device.is_active" class="text-xs text-green-400 font-bold">Active</div>
                            <div v-else-if="selectedDeviceId === device.id" class="text-xs text-green-400 font-bold">
                                Selected ✓
                            </div>
                        </div>
                    </button>
                </div>

                <div v-else-if="!isLoadingDevices" class="p-4 bg-white/5 rounded-lg text-center text-sm text-white/60">
                    <div class="mb-2">No devices found</div>
                    <div class="text-xs">
                        Open Spotify on your phone or computer and start playing something once, then click Refresh.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
