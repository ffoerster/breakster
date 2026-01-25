<script setup>
import QrcodeVue from 'qrcode.vue';
import Sortable from 'sortablejs';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import AudioPlayer from '../../Ui/AudioPlayer.vue';
import CountdownRing from '../../Ui/CountdownRing.vue';
import GlassCard from '../../Ui/GlassCard.vue';
import PlayersList from '../PlayersList.vue';

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
    remainingSeconds: {
        type: Number,
        required: true,
    },
    isPlacementLocked: {
        type: Boolean,
        required: true,
    },
    isPlacing: {
        type: Boolean,
        required: true,
    },
    placeError: {
        type: String,
        required: true,
    },
    place: {
        type: Function,
        required: true,
    },
    revealRound: {
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
});

const mysterySourceEl = ref(null);
const placementListEl = ref(null);
let mysterySortable = null;
let placementSortable = null;

const shouldEnablePlacementDnd = computed(() => {
    return Boolean(!props.isPlacementLocked);
});

const shouldEnableMysterySourceDnd = computed(() => {
    return Boolean(!props.isPlacementLocked && !props.state.me?.has_placed);
});

const placementIndex = computed(() => {
    if (!props.state.me?.has_placed) {
        return null;
    }

    const afterId = props.state.me?.placement_inserted_after_track_id ?? null;
    const timeline = props.state.timeline ?? [];

    if (afterId === null) {
        return 0;
    }

    const afterIndex = timeline.findIndex((t) => t.track_id === afterId);
    if (afterIndex === -1) {
        return 0;
    }

    return afterIndex + 1;
});

const timelineRenderItems = computed(() => {
    const timeline = props.state.timeline ?? [];

    const items = timeline.map((t) => ({
        kind: 'track',
        ...t,
    }));

    if (placementIndex.value === null) {
        return items;
    }

    items.splice(placementIndex.value, 0, {
        kind: 'mystery',
        id: 'mystery',
    });

    return items;
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

    if (shouldEnableMysterySourceDnd.value) {
        mysterySortable = Sortable.create(mysterySourceEl.value, {
            group: {
                name: 'placement',
                pull: 'clone',
                put: false,
            },
            sort: false,
            animation: 200,
            easing: 'cubic-bezier(0.2, 0, 0, 1)',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            chosenClass: 'sortable-chosen',
            delay: 120,
            delayOnTouchOnly: true,
            touchStartThreshold: 5,
            fallbackOnBody: true,
        });
    }

    placementSortable = Sortable.create(placementListEl.value, {
        group: {
            name: 'placement',
            pull: false,
            put: true,
        },
        sort: true,
        draggable: '.timeline-item',
        filter: '.timeline-track',
        preventOnFilter: false,
        animation: 200,
        easing: 'cubic-bezier(0.2, 0, 0, 1)',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        chosenClass: 'sortable-chosen',
        fallbackOnBody: true,
        swapThreshold: 0.65,
        onAdd: async (evt) => {
            if (props.isPlacementLocked) {
                evt.item.remove();
                return;
            }

            const newIndex = evt.newIndex;
            const timeline = props.state.timeline ?? [];

            let insertedAfterTrackId = null;
            if (typeof newIndex === 'number' && newIndex > 0) {
                insertedAfterTrackId = timeline[newIndex - 1]?.track_id ?? null;
            }

            evt.item.remove();

            await props.place(insertedAfterTrackId);
        },
        onEnd: async (evt) => {
            if (props.isPlacementLocked) {
                return;
            }

            if (!evt.item?.classList?.contains('timeline-guess')) {
                return;
            }

            const newIndex = evt.newIndex;
            const timeline = props.state.timeline ?? [];

            let insertedAfterTrackId = null;
            if (typeof newIndex === 'number' && newIndex > 0) {
                insertedAfterTrackId = timeline[newIndex - 1]?.track_id ?? null;
            }

            await props.place(insertedAfterTrackId);
        },
    });
};

watch(
    () => shouldEnablePlacementDnd.value,
    async (enabled) => {
        await nextTick();

        if (enabled) {
            initPlacementDnD();
        } else {
            destroyPlacementDnD();
        }
    },
    { immediate: true },
);

watch(
    () => shouldEnableMysterySourceDnd.value,
    async () => {
        await nextTick();

        if (shouldEnablePlacementDnd.value) {
            initPlacementDnD();
        }
    },
);

onMounted(async () => {
    await nextTick();

    if (shouldEnablePlacementDnd.value) {
        initPlacementDnD();
    }
});

onBeforeUnmount(() => {
    destroyPlacementDnD();
});

const placementsProgress = computed(() => {
    const total = props.state.round?.players_count ?? 0;
    if (total <= 0) {
        return 0;
    }

    const placed = props.state.round?.placements_count ?? 0;

    return Math.min(100, Math.max(0, Math.round((placed / total) * 100)));
});
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
            <div class="lg:col-span-4 lg:sticky lg:top-6 space-y-6">
                <GlassCard class="p-8 text-center">
                    <div class="text-sm text-gray-400 mb-2">Round {{ state.round.round_number }}</div>
                    <h2 class="text-2xl font-bold mb-6">Listen and place this track!</h2>

                    <div v-if="isHost" class="max-w-sm mx-auto mb-6">
                        <div
                            v-if="isSpotifyProvider && state.room.spotify_device_id"
                            class="mb-6"
                        >
                            <button
                                type="button"
                                class="btn-interactive w-full px-6 py-4 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg font-bold flex items-center justify-center gap-3"
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
                            <div class="mt-2 text-xs text-gray-400 text-center">Plays on your selected Spotify device</div>
                        </div>

                        <div class="text-sm text-gray-400 mb-3">
                            Scan to open in {{ state.room.playback_provider?.name ?? 'your app' }}
                        </div>

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
                            class="btn-interactive mt-3 px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg font-bold"
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
                            class="btn-interactive inline-flex items-center justify-center gap-2 px-6 py-4 bg-blue-600 hover:bg-blue-700 rounded-lg font-bold mt-4"
                        >
                            Open in {{ state.round.playback.provider_name }}
                        </a>
                    </div>

                    <p class="mt-4 text-gray-400 text-sm">No titles, no artists, no years — just your ears.</p>
                </GlassCard>
            </div>

            <div class="lg:col-span-5 space-y-6">
                <GlassCard class="p-6">
                    <h3 class="text-lg font-bold mb-4 text-center">Current Track</h3>
                    <div
                        ref="mysterySourceEl"
                        class="select-none"
                        :class="isPlacementLocked ? 'opacity-50' : ''"
                    >
                        <div
                            class="timeline-item timeline-guess flex items-center gap-3 p-4 bg-linear-to-r from-accent-600/30 to-pink-600/30 border-2 border-accent-500/50 rounded-lg"
                            :class="
                                isPlacementLocked
                                    ? 'cursor-not-allowed'
                                    : state.me.has_placed
                                        ? 'cursor-default'
                                        : 'cursor-grab active:cursor-grabbing'
                            "
                        >
                            <div class="w-16 h-16 rounded bg-accent-900/60 flex items-center justify-center shrink-0">
                                <svg class="w-8 h-8 text-accent-200" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M10 2a5 5 0 00-5 5v2a2 2 0 00-2 2v5a2 2 0 002 2h10a2 2 0 002-2v-5a2 2 0 00-2-2H7V7a3 3 0 015.905-.75 1 1 0 001.937-.5A5.002 5.002 0 0010 2z"
                                    />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0 text-center">
                                <div class="text-2xl font-black text-accent-300">???</div>
                                <div class="text-sm text-gray-300 font-medium">Mystery Track</div>
                                <div class="text-xs text-gray-400 mt-1">
                                    <span v-if="isPlacementLocked">Locked</span>
                                    <span v-else-if="state.me.has_placed">Move it in your timeline</span>
                                    <span v-else>Drag into your timeline</span>
                                </div>
                            </div>

                            <div class="w-10 h-10 shrink-0 flex items-center justify-center">
                                <div
                                    v-if="isPlacing"
                                    class="w-6 h-6 rounded-full border-2 border-gray-300/30 border-t-gray-200 animate-spin"
                                />
                                <div
                                    v-else-if="state.me.has_placed"
                                    class="w-8 h-8 rounded-full bg-green-500/20 border border-green-500 flex items-center justify-center"
                                    title="Placement saved"
                                >
                                    <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </GlassCard>

                <GlassCard class="p-8">
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <h3 class="text-xl font-bold">Your Timeline</h3>
                        <div class="text-xs text-gray-400">
                            {{ state.round.placements_count }} / {{ state.round.players_count }} placed
                        </div>
                    </div>

                    <div class="h-2 rounded bg-gray-700/60 overflow-hidden mb-6">
                        <div class="h-full bg-accent-500/70" :style="{ width: `${placementsProgress}%` }" />
                    </div>

                    <Transition name="feedback-slide">
                        <div
                            v-if="isPlacementLocked"
                            class="flex items-center justify-center gap-4 p-4 bg-blue-500/20 border border-blue-500 rounded-lg mb-4"
                        >
                            <CountdownRing :remaining="remainingSeconds" :total="3" />
                            <span class="font-medium">Placement unlocks soon...</span>
                        </div>
                    </Transition>

                    <Transition name="feedback-slide">
                        <div
                            v-if="state.me.has_placed && !isPlacementLocked"
                            class="p-4 bg-green-500/20 border border-green-500 rounded-lg text-center mb-4"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg class="w-5 h-5 text-green-400 animate-scale-pop" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <span>Your guess is saved. You can change it until reveal.</span>
                            </div>
                        </div>
                    </Transition>

                    <Transition name="feedback-slide">
                        <div v-if="placeError" class="p-4 bg-red-500/20 border border-red-500 rounded-lg text-center mb-4 animate-shake">
                            {{ placeError }}
                        </div>
                    </Transition>

                    <div class="text-sm text-gray-400 mb-3">
                        <span v-if="!state.me.has_placed">Drop the mystery card between years.</span>
                        <span v-else>Drag your guess within the timeline to update it.</span>
                    </div>

                    <ul
                        ref="placementListEl"
                        class="space-y-2 min-h-[4rem] p-3 rounded-lg border-2 border-dashed lg:max-h-[52vh] lg:overflow-auto"
                        :class="
                            shouldEnablePlacementDnd
                                ? 'border-accent-500/40 bg-gray-900/20'
                                : 'border-gray-700/60 bg-gray-900/10'
                        "
                    >
                        <li
                            v-for="item in timelineRenderItems"
                            :key="item.kind === 'track' ? item.track_id : item.id"
                            class="flex items-center gap-3 p-4 rounded-lg"
                            :class="
                                item.kind === 'mystery'
                                    ? 'timeline-item timeline-guess bg-accent-500/10 border border-accent-500/30 cursor-grab active:cursor-grabbing'
                                    : 'timeline-item timeline-track bg-gray-700/30'
                            "
                        >
                            <template v-if="item.kind === 'track'">
                                <img
                                    v-if="item.artwork_url"
                                    :src="item.artwork_url"
                                    alt=""
                                    class="w-16 h-16 rounded object-cover shrink-0"
                                />
                                <div v-else class="w-16 h-16 rounded bg-gray-600 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <div class="text-2xl md:text-3xl font-black text-accent-400 leading-none mb-1">
                                        {{ item.year }}
                                    </div>
                                    <div class="font-medium truncate">{{ item.title }}</div>
                                    <div class="text-sm text-gray-400 truncate">{{ item.artist }}</div>
                                </div>
                            </template>

                            <template v-else>
                                <div class="w-16 h-16 rounded bg-accent-900/50 flex items-center justify-center shrink-0">
                                    <svg class="w-8 h-8 text-accent-200" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M10 2a5 5 0 00-5 5v2a2 2 0 00-2 2v5a2 2 0 002 2h10a2 2 0 002-2v-5a2 2 0 00-2-2H7V7a3 3 0 015.905-.75 1 1 0 001.937-.5A5.002 5.002 0 0010 2z"
                                        />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-2xl md:text-3xl font-black text-accent-200 leading-none mb-1">???</div>
                                    <div class="font-medium truncate">Your guess</div>
                                    <div class="text-sm text-gray-400 truncate">Drag to reposition</div>
                                </div>

                                <div class="w-8 h-8 shrink-0 flex items-center justify-center">
                                    <div
                                        v-if="isPlacing"
                                        class="w-5 h-5 rounded-full border-2 border-gray-300/30 border-t-gray-200 animate-spin"
                                    />
                                    <div v-else class="w-6 h-6 rounded-full bg-green-500/20 border border-green-500 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </template>
                        </li>
                    </ul>

                    <div v-if="(state.timeline?.length ?? 0) === 0" class="text-center text-gray-500 mt-3">
                        Drop into the empty box to place as your first track
                    </div>
                </GlassCard>

                <GlassCard class="p-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="text-sm text-gray-400">
                            {{ state.round.placements_count }} / {{ state.round.players_count }} players have placed
                        </div>
                        <button
                            v-if="isHost"
                            type="button"
                            class="btn-interactive px-6 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="state.round.players_count === 0"
                            @click="revealRound"
                        >
                            Reveal Answers
                        </button>
                        <div v-else class="text-sm text-gray-400">Waiting for host to reveal…</div>
                    </div>
                </GlassCard>
            </div>

            <div class="lg:col-span-3 lg:sticky lg:top-6 space-y-6">
                <GlassCard class="p-8">
                    <h2 class="text-xl font-bold mb-4">Players</h2>
                    <PlayersList :players="state.players" show-score />
                </GlassCard>
            </div>
        </div>
    </div>
</template>
