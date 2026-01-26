<script setup>
import QrcodeVue from 'qrcode.vue';

import AudioPlayer from '../../Ui/AudioPlayer.vue';
import GlassCard from '../../Ui/GlassCard.vue';

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    isHost: {
        type: Boolean,
        required: true,
    },
    playUrl: {
        type: String,
        required: true,
    },
    copyPlayUrl: {
        type: Function,
        required: true,
    },
    isSpotifyProvider: {
        type: Boolean,
        required: true,
    },
    spotifyPlayError: {
        type: String,
        required: true,
    },
    isPlayingSpotify: {
        type: Boolean,
        required: true,
    },
    playSpotifyTrack: {
        type: Function,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const qrSize = () => (props.compact ? 190 : 220);
const outerPaddingClass = () => (props.compact ? 'p-6' : 'p-8');
</script>

<template>
    <GlassCard :class="outerPaddingClass()" class="text-center">
        <div class="text-sm text-gray-400 mb-2">Round {{ state.round.round_number }}</div>
        <h2 class="text-2xl font-bold mb-6">Listen and place this track!</h2>

        <div v-if="isHost" class="max-w-sm mx-auto mb-6">
            <div
                v-if="isSpotifyProvider && state.room.spotify_device_id"
                class="mb-6"
            >
                <button
                    type="button"
                    class="btn-interactive w-full min-h-[44px] px-6 py-3 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg font-bold flex items-center justify-center gap-3"
                    :disabled="isPlayingSpotify"
                    @click="playSpotifyTrack"
                >
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.540.780.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"
                        />
                    </svg>
                    <span v-if="isPlayingSpotify">Playing...</span>
                    <span v-else>Play on Spotify</span>
                </button>
                <div v-if="spotifyPlayError" class="mt-2 text-sm text-red-400 text-center">
                    {{ spotifyPlayError }}
                </div>
                <div v-if="!compact" class="mt-2 text-xs text-gray-400 text-center">Plays on your selected Spotify device</div>
            </div>

            <div class="text-sm text-gray-400 mb-3">
                Scan to open in {{ state.room.playback_provider?.name ?? 'your app' }}
            </div>

            <QrcodeVue
                v-if="playUrl"
                :value="playUrl"
                :size="qrSize()"
                level="M"
                class="mx-auto bg-white p-3 rounded-xl"
            />

            <div class="mt-3 text-xs text-gray-400 break-all font-mono">{{ playUrl }}</div>

            <button
                type="button"
                class="btn-interactive mt-3 min-h-[44px] px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg font-bold"
                @click="copyPlayUrl"
            >
                Copy link
            </button>
        </div>

        <div class="max-w-sm mx-auto">
            <AudioPlayer
                v-if="state.round.playback?.preview_url"
                :src="state.round.playback.preview_url"
                autoplay
            />

            <a
                v-if="state.round.playback?.external_url"
                :href="state.round.playback.external_url"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-interactive inline-flex items-center justify-center gap-2 min-h-[44px] px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg font-bold mt-4"
            >
                Open in {{ state.round.playback.provider_name }}
            </a>
        </div>

        <p v-if="!compact" class="mt-4 text-gray-400 text-sm">No titles, no artists, no years — just your ears.</p>
    </GlassCard>
</template>
