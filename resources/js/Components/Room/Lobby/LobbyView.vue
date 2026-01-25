<script setup>
import GlassCard from '../../Ui/GlassCard.vue';
import PlayersList from '../PlayersList.vue';
import ProviderPicker from './ProviderPicker.vue';
import SpotifyHostSetup from './SpotifyHostSetup.vue';

defineProps({
    state: {
        type: Object,
        required: true,
    },
    isHost: {
        type: Boolean,
        required: true,
    },
    canStart: {
        type: Boolean,
        required: true,
    },
    selectProvider: {
        type: Function,
        required: true,
    },
    startGame: {
        type: Function,
        required: true,
    },
    isSpotifyProvider: {
        type: Boolean,
        required: true,
    },
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
    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-7 space-y-6">
            <GlassCard class="p-8">
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-50">Listening App</h2>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">Host chooses how players open tracks during the game.</p>

                <div v-if="state.room.playback_provider" class="flex items-center gap-2 text-green-600 dark:text-green-400 mb-4">
                    <span class="font-medium">Listening via {{ state.room.playback_provider.name }}</span>
                </div>
                <div v-else class="flex items-center gap-2 text-amber-600 dark:text-amber-400 mb-4">
                    <span class="font-medium">Waiting for host to choose listening app…</span>
                </div>

                <ProviderPicker
                    :providers="state.providers"
                    :selected-provider-id="state.room.playback_provider?.id ?? null"
                    :is-host="isHost"
                    :on-select="selectProvider"
                />

                <div v-if="isHost && isSpotifyProvider">
                    <SpotifyHostSetup
                        :has-spotify-token="hasSpotifyToken"
                        :spotify-devices="spotifyDevices"
                        :spotify-error="spotifyError"
                        :is-loading-devices="isLoadingDevices"
                        :selected-device-id="selectedDeviceId"
                        :connect-spotify="connectSpotify"
                        :load-spotify-devices="loadSpotifyDevices"
                        :select-spotify-device="selectSpotifyDevice"
                    />
                </div>

                <div class="mt-6 flex gap-4">
                    <button
                        v-if="isHost"
                        type="button"
                        class="btn-interactive flex-1 py-4 px-6 font-bold rounded-lg"
                        :class="
                            canStart
                                ? 'bg-green-600 hover:bg-green-700 text-white dark:bg-green-600 dark:hover:bg-green-700'
                                : 'bg-gray-300 text-gray-500 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500'
                        "
                        :disabled="!canStart"
                        @click="startGame"
                    >
                        Start Game
                    </button>
                    <div v-else class="flex-1 py-4 px-6 bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400 font-bold rounded-lg text-center">
                        Waiting for host to start…
                    </div>
                </div>
            </GlassCard>
        </div>

        <div class="lg:col-span-5">
            <GlassCard class="p-8">
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-50">Players ({{ state.players.length }})</h2>
                <PlayersList :players="state.players" show-host-badge />
            </GlassCard>
        </div>
    </div>
</template>
