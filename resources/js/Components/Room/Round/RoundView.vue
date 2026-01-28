<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import BottomActionBar from '../../Ui/BottomActionBar.vue';
import BottomSheet from '../../Ui/BottomSheet.vue';
import CountdownRing from '../../Ui/CountdownRing.vue';
import GlassCard from '../../Ui/GlassCard.vue';
import PlayersList from '../PlayersList.vue';
import AmenButton from './AmenButton.vue';
import AmenGuessForm from './AmenGuessForm.vue';
import AmenResult from './AmenResult.vue';
import RoundListenPanel from './RoundListenPanel.vue';

let sortableLib = null;
let sortableInitRunId = 0;

const getSortable = async () => {
    if (sortableLib) {
        return sortableLib;
    }

    const mod = await import('sortablejs');
    sortableLib = mod.default;

    return sortableLib;
};

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
    copiedPlayUrl: {
        type: Boolean,
        default: false,
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
    openDetails: {
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
    // AMEN props
    claimAmen: {
        type: Function,
        required: true,
    },
    isClaimingAmen: {
        type: Boolean,
        required: true,
    },
    amenClaimError: {
        type: String,
        default: '',
    },
    submitAmenGuess: {
        type: Function,
        required: true,
    },
    isSubmittingAmenGuess: {
        type: Boolean,
        required: true,
    },
    amenGuessError: {
        type: String,
        default: '',
    },
});

const isListenOpen = ref(false);

const openListen = () => {
    isListenOpen.value = true;
};

const closeListen = () => {
    isListenOpen.value = false;
};

const mysterySourceEl = ref(null);
const placementListEl = ref(null);
let mysterySortable = null;
let placementSortable = null;

const optimisticPlaced = ref(false);

const hasGuessInTimeline = computed(() => Boolean(props.state.me?.has_placed) || optimisticPlaced.value);

const queuedInsertedAfterTrackId = ref(undefined);

const requestPlace = async (insertedAfterTrackId) => {
    if (props.isPlacementLocked) {
        return;
    }

    if (props.isPlacing) {
        queuedInsertedAfterTrackId.value = insertedAfterTrackId;
        return;
    }

    await props.place(insertedAfterTrackId);
};

watch(
    () => props.isPlacing,
    (placing) => {
        if (placing) {
            return;
        }

        if (queuedInsertedAfterTrackId.value === undefined) {
            return;
        }

        const next = queuedInsertedAfterTrackId.value;
        queuedInsertedAfterTrackId.value = undefined;
        requestPlace(next);
    },
);

watch(
    () => props.state.round?.id,
    () => {
        queuedInsertedAfterTrackId.value = undefined;
        optimisticPlaced.value = false;
    },
);

watch(
    () => props.placeError,
    (error) => {
        if (!error) {
            return;
        }

        if (props.state.me?.has_placed) {
            return;
        }

        optimisticPlaced.value = false;
    },
);

const shouldEnablePlacementDnd = computed(() => {
    return Boolean(!props.isPlacementLocked);
});

const shouldEnableMysterySourceDnd = computed(() => {
    return Boolean(!props.isPlacementLocked && !hasGuessInTimeline.value);
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

const initPlacementDnD = async () => {
    if (!placementListEl.value) {
        return;
    }

    const runId = ++sortableInitRunId;

    destroyPlacementDnD();

    const Sortable = await getSortable();
    if (runId !== sortableInitRunId) {
        return;
    }

    if (shouldEnableMysterySourceDnd.value && mysterySourceEl.value) {
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

            optimisticPlaced.value = true;

            const newIndex = evt.newIndex;
            const timeline = props.state.timeline ?? [];

            let insertedAfterTrackId = null;
            if (typeof newIndex === 'number' && newIndex > 0) {
                insertedAfterTrackId = timeline[newIndex - 1]?.track_id ?? null;
            }

            evt.item.remove();

            await requestPlace(insertedAfterTrackId);
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

            await requestPlace(insertedAfterTrackId);
        },
    });
}; 

watch(
    () => shouldEnablePlacementDnd.value,
    async (enabled) => {
        await nextTick();

        if (enabled) {
            await initPlacementDnD();
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
            await initPlacementDnD();
        }
    },
);

onMounted(async () => {
    await nextTick();

    if (shouldEnablePlacementDnd.value) {
        await initPlacementDnD();
    }
});

onBeforeUnmount(() => {
    destroyPlacementDnD();
    sortableInitRunId += 1;
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
            <div class="hidden lg:block lg:col-span-4 lg:sticky lg:top-6 space-y-6">
                <RoundListenPanel
                    :state="state"
                    :is-host="isHost"
                    :play-url="playUrl"
                    :copy-play-url="copyPlayUrl"
                    :copied-play-url="copiedPlayUrl"
                    :is-spotify-provider="isSpotifyProvider"
                    :spotify-play-error="spotifyPlayError"
                    :is-playing-spotify="isPlayingSpotify"
                    :play-spotify-track="playSpotifyTrack"
                />
            </div>

            <div class="lg:col-span-5 space-y-6">
                <GlassCard class="p-6">
                    <h3 class="text-lg font-bold mb-4 text-center">Current Track</h3>

                    <Transition name="feedback-slide" mode="out-in">
                        <div
                            v-if="!hasGuessInTimeline"
                            key="mystery-source"
                            ref="mysterySourceEl"
                            class="select-none"
                            :class="isPlacementLocked ? 'opacity-50' : ''"
                        >
                            <div
                                class="timeline-item timeline-guess flex items-center gap-3 p-4 bg-linear-to-r from-accent-600/30 to-pink-600/30 border-2 border-accent-500/50 rounded-lg"
                                :class="isPlacementLocked ? 'cursor-not-allowed' : 'cursor-grab active:cursor-grabbing'"
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
                                        <span v-else>Drag into your timeline</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            key="mystery-status"
                            class="select-none"
                            :class="isPlacementLocked ? 'opacity-60' : ''"
                        >
                            <div class="flex items-center justify-between gap-4 px-4 py-3 rounded-lg border border-white/10 bg-gray-900/40">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded bg-accent-900/50 flex items-center justify-center shrink-0">
                                        <svg class="w-6 h-6 text-accent-200" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                d="M10 2a5 5 0 00-5 5v2a2 2 0 00-2 2v5a2 2 0 002 2h10a2 2 0 002-2v-5a2 2 0 00-2-2H7V7a3 3 0 015.905-.75 1 1 0 001.937-.5A5.002 5.002 0 0010 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-gray-100 truncate">Guess placed</div>
                                        <div class="text-xs text-gray-400">
                                            <span v-if="isPlacementLocked">Locked</span>
                                            <span v-else>Drag within your timeline to reposition</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-10 h-10 shrink-0 flex items-center justify-center">
                                    <div
                                        v-if="isPlacing"
                                        class="w-6 h-6 rounded-full border-2 border-gray-300/30 border-t-gray-200 animate-spin"
                                    />
                                    <div
                                        v-else
                                        class="w-8 h-8 rounded-full bg-green-500/15 border border-green-500/50 flex items-center justify-center"
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
                    </Transition>
                </GlassCard>

                <!-- AMEN Section -->
                <div v-if="state.round?.amen" class="space-y-4">
                    <!-- AMEN Button (when can claim) -->
                    <GlassCard v-if="state.round.amen.can_claim && !state.round.amen.claimed_by" class="p-6 hidden lg:block">
                        <AmenButton
                            :can-claim="state.round.amen.can_claim"
                            :is-claiming="isClaimingAmen"
                            :error="amenClaimError"
                            :claim-amen="claimAmen"
                        />
                    </GlassCard>

                    <!-- AMEN Guess Form (for claimant who hasn't guessed yet) -->
                    <template v-if="state.round.amen.claimed_by">
                        <AmenGuessForm
                            v-if="state.round.amen.is_claimant && !state.round.amen.has_guess"
                            :is-submitting="isSubmittingAmenGuess"
                            :error="amenGuessError"
                            :submit-guess="submitAmenGuess"
                        />

                        <!-- AMEN Result (when resolved) -->
                        <AmenResult
                            v-else-if="state.round.amen.resolved"
                            :claimant-name="state.round.amen.claimant_name"
                            :guess="state.round.amen.guess"
                            :actual="state.round.amen.actual"
                            :correct="state.round.amen.correct"
                            :awarded-points="state.round.amen.awarded_points"
                            :votes="state.round.amen.votes"
                        />

                        <!-- Waiting state for claimant after guess -->
                        <GlassCard
                            v-else-if="state.round.amen.is_claimant && state.round.amen.has_guess"
                            class="p-6 bg-gradient-to-br from-amber-500/20 to-orange-600/20 border border-amber-500/50"
                        >
                            <p class="text-center text-amber-300 font-medium">
                                Waiting for reveal...
                            </p>
                        </GlassCard>
                    </template>
                </div>

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
                                    ? shouldEnablePlacementDnd
                                        ? 'timeline-item timeline-guess bg-accent-500/10 border border-accent-500/30 cursor-grab active:cursor-grabbing'
                                        : 'timeline-item timeline-guess bg-accent-500/10 border border-accent-500/20 cursor-not-allowed opacity-70'
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
                                    <div class="text-[clamp(1.6rem,6vw,2.25rem)] font-black text-accent-400 leading-none mb-1">
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
                                    <div class="text-[clamp(1.6rem,6vw,2.25rem)] font-black text-accent-200 leading-none mb-1">???</div>
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

                <GlassCard class="p-4 hidden lg:block">
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
                    type="button"
                    class="btn-interactive min-h-[44px] px-4 py-3 rounded-xl font-bold bg-gray-800/70 text-white"
                    @click="openListen"
                >
                    Listen
                </button>

                <div v-if="state.round?.amen?.can_claim && !state.round.amen.claimed_by" class="col-span-2">
                    <AmenButton
                        variant="compact"
                        :can-claim="state.round.amen.can_claim"
                        :is-claiming="isClaimingAmen"
                        :error="amenClaimError"
                        :claim-amen="claimAmen"
                    />
                </div>

                <button
                    v-if="isHost"
                    type="button"
                    class="btn-interactive col-span-2 min-h-[44px] px-4 py-3 rounded-xl font-bold bg-yellow-600 text-white"
                    :disabled="state.round.players_count === 0"
                    @click="revealRound"
                >
                    Reveal Answers
                </button>
            </div>
        </BottomActionBar>

        <BottomSheet
            :open="isListenOpen"
            title="Listen"
            max-width-class="max-w-6xl"
            @close="closeListen"
        >
            <div v-if="isListenOpen" class="space-y-4">
                <RoundListenPanel
                    :state="state"
                    :is-host="isHost"
                    :play-url="playUrl"
                    :copy-play-url="copyPlayUrl"
                    :copied-play-url="copiedPlayUrl"
                    :is-spotify-provider="isSpotifyProvider"
                    :spotify-play-error="spotifyPlayError"
                    :is-playing-spotify="isPlayingSpotify"
                    :play-spotify-track="playSpotifyTrack"
                    compact
                />
            </div>
        </BottomSheet>
    </div>
</template>
