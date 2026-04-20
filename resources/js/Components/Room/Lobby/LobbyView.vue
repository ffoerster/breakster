<script setup>
import { computed } from "vue";
import BottomActionBar from "../../Ui/BottomActionBar.vue";
import GlassCard from "../../Ui/GlassCard.vue";
import PlayersList from "../PlayersList.vue";
import GameSettingsPanel from "./GameSettingsPanel.vue";
import ProviderPicker from "./ProviderPicker.vue";
import ReadyButton from "./ReadyButton.vue";
import SpotifyHostSetup from "./SpotifyHostSetup.vue";

const props = defineProps({
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
        default: "",
    },
    updateSettings: {
        type: Function,
        required: true,
    },
    isUpdatingSettings: {
        type: Boolean,
        default: false,
    },
});

const allGuestsReady = computed(() => {
    if (!props.isHost) {
        return false;
    }

    const total = props.state.room.ready_total ?? 0;
    const ready = props.state.room.ready_count ?? 0;

    return total > 0 && ready >= total;
});
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-7 space-y-6">
            <GlassCard class="p-8">
                <h2 class="text-xl font-bold mb-4 text-white font-display">Listening App</h2>
                <p class="text-white/70 text-sm mb-4">
                    Host chooses how players open tracks during the game.
                </p>

                <div
                    v-if="state.room.playback_provider"
                    class="flex items-center gap-2 text-green-400 mb-4"
                >
                    <span class="font-medium"
                        >Listening via {{ state.room.playback_provider.name }}</span
                    >
                </div>
                <div v-else class="flex items-center gap-2 text-amber-300 mb-4">
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

                <div v-if="isHost" class="mt-6 border-t border-white/10 pt-6">
                    <GameSettingsPanel
                        :settings="state.room"
                        :on-update="updateSettings"
                        :is-updating="isUpdatingSettings"
                    />
                </div>

                <div class="mt-6 hidden lg:block">
                    <div v-if="!isHost">
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
                <h2 class="text-xl font-bold mb-4 text-white font-display">
                    Players ({{ state.players.length }})
                </h2>
                <PlayersList :players="state.players" show-host-badge show-ready-status />
            </GlassCard>
        </div>

        <BottomActionBar class="lg:hidden" max-width-class="max-w-6xl">
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="btn-interactive btn-secondary min-h-11 px-4 py-3 rounded-xl font-bold text-white"
                    @click="openDetails"
                >
                    Details
                </button>

                <button
                    v-if="isHost"
                    type="button"
                    class="btn-interactive min-h-11 px-4 py-3 font-bold rounded-xl"
                    :class="[
                        canStart ? 'btn-success' : 'btn-secondary text-white/40',
                        allGuestsReady && canStart ? 'pulse-ready' : '',
                    ]"
                    :disabled="!canStart"
                    @click="startGame"
                >
                    Start
                </button>

                <button
                    v-else-if="!state.me?.is_ready"
                    type="button"
                    class="btn-interactive btn-primary min-h-11 px-4 py-3 font-bold rounded-xl text-white"
                    :disabled="isMarkingReady"
                    @click="markReady"
                >
                    <span v-if="isMarkingReady">...</span>
                    <span v-else>Ready</span>
                </button>

                <div
                    v-else
                    class="min-h-11 px-4 py-3 bg-accent-500/15 border border-accent-500/40 text-accent-200 font-bold rounded-xl text-center flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    Ready
                </div>
            </div>
        </BottomActionBar>
    </div>
</template>
