<script setup>
import GlassCard from '../../Ui/GlassCard.vue';
import BottomActionBar from '../../Ui/BottomActionBar.vue';
import PlayersList from '../PlayersList.vue';
import ProviderPicker from './ProviderPicker.vue';
import SpotifyHostSetup from './SpotifyHostSetup.vue';
import ReadyButton from './ReadyButton.vue';

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
    openDetails: {
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
    markReady: {
        type: Function,
        required: true,
    },
    isMarkingReady: {
        type: Boolean,
        default: false,
    },
    markReadyError: {
        type: String,
        default: '',
    },
    togglePlacementTimer: {
        type: Function,
        required: true,
    },
    isTogglingPlacementTimer: {
        type: Boolean,
        default: false,
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

                <div v-if="isHost" class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Game Settings</h3>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="state.room.placement_timer_enabled"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                            :class="state.room.placement_timer_enabled ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'"
                            :disabled="isTogglingPlacementTimer"
                            @click="togglePlacementTimer(!state.room.placement_timer_enabled)"
                        >
                            <span
                                :class="state.room.placement_timer_enabled ? 'translate-x-5' : 'translate-x-0'"
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            />
                        </button>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            Round timer ({{ state.room.placement_timer_enabled ? 'enabled' : 'disabled' }})
                        </span>
                    </label>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                        When enabled, players have limited time to place their guess each round.
                    </p>
                </div>

                <div class="mt-6 hidden lg:block">
                    <div v-if="isHost" class="flex gap-4">
                        <button
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
                    </div>
                    <div v-else>
                        <ReadyButton
                            :is-ready="state.me?.is_ready ?? false"
                            :is-marking="isMarkingReady"
                            :mark-ready="markReady"
                            :ready-count="state.room.ready_count ?? 0"
                            :total-players="state.room.ready_total ?? 0"
                            :error="markReadyError"
                        />
                    </div>
                </div>
            </GlassCard>
        </div>

        <div class="lg:col-span-5">
            <GlassCard class="p-8">
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-50">Players ({{ state.players.length }})</h2>
                <PlayersList :players="state.players" show-host-badge show-ready-status />
            </GlassCard>
        </div>

        <BottomActionBar class="lg:hidden" max-width-class="max-w-6xl">
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="btn-interactive min-h-[44px] px-4 py-3 rounded-xl font-bold bg-gray-800/70 text-white"
                    @click="openDetails"
                >
                    Details
                </button>

                <button
                    v-if="isHost"
                    type="button"
                    class="btn-interactive min-h-[44px] px-4 py-3 font-bold rounded-xl"
                    :class="canStart ? 'bg-green-600 text-white' : 'bg-gray-700 text-gray-400'"
                    :disabled="!canStart"
                    @click="startGame"
                >
                    Start
                </button>

                <button
                    v-else-if="!state.me?.is_ready"
                    type="button"
                    class="btn-interactive min-h-[44px] px-4 py-3 font-bold rounded-xl bg-blue-600 text-white"
                    :disabled="isMarkingReady"
                    @click="markReady"
                >
                    <span v-if="isMarkingReady">...</span>
                    <span v-else>Ready</span>
                </button>

                <div
                    v-else
                    class="min-h-[44px] px-4 py-3 bg-blue-500/20 border border-blue-500 text-blue-300 font-bold rounded-xl text-center flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Ready
                </div>
            </div>
        </BottomActionBar>
    </div>
</template>
