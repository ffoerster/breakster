<script setup>
import { computed, inject, ref, watch } from "vue";
import { useHaptics } from "../../../Composables/useHaptics";
import BottomActionBar from "../../Ui/BottomActionBar.vue";
import BottomSheet from "../../Ui/BottomSheet.vue";
import CountdownRing from "../../Ui/CountdownRing.vue";
import GlassCard from "../../Ui/GlassCard.vue";
import PlayersList from "../PlayersList.vue";
import AmenButton from "./AmenButton.vue";
import AmenGuessForm from "./AmenGuessForm.vue";
import AmenResult from "./AmenResult.vue";
import RoundListenPanel from "./RoundListenPanel.vue";
import TimelineDropZone from "./TimelineDropZone.vue";

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
	placementTimeRemaining: {
		type: Number,
		required: true,
	},
	isPlacementTimeExpired: {
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
	undoPlacement: {
		type: Function,
		required: true,
	},
	isUndoingPlacement: {
		type: Boolean,
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
		default: "",
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
		default: "",
	},
});

const gameSounds = inject("gameSounds", null);
const haptics = useHaptics();

const isListenOpen = ref(false);

const openListen = () => {
	isListenOpen.value = true;
};

const closeListen = () => {
	isListenOpen.value = false;
};

const hasGuessInTimeline = computed(() => Boolean(props.state.me?.has_placed));

const placementInsertAfterTrackId = computed(
	() => props.state.me?.placement_inserted_after_track_id ?? null,
);

const timelineWithDropZones = computed(() => {
	const timeline = props.state.timeline ?? [];
	const items = [];

	// Drop zone at the beginning (insertAfterTrackId = null)
	items.push({
		kind: "drop-zone",
		insertAfterTrackId: null,
		isActive:
			hasGuessInTimeline.value && placementInsertAfterTrackId.value === null,
	});

	for (const track of timeline) {
		items.push({ kind: "track", ...track });
		items.push({
			kind: "drop-zone",
			insertAfterTrackId: track.track_id,
			isActive:
				hasGuessInTimeline.value &&
				placementInsertAfterTrackId.value === track.track_id,
		});
	}

	return items;
});

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
	},
);

const handleDropZonePlace = async (insertAfterTrackId) => {
	if (props.isPlacementLocked || props.isPlacementTimeExpired) {
		return;
	}

	gameSounds?.playClick();
	haptics.medium();
	await requestPlace(insertAfterTrackId);
};

const handleUndo = async () => {
	if (props.isPlacementLocked || props.isPlacementTimeExpired) {
		return;
	}

	gameSounds?.playClick();
	haptics.light();
	await props.undoPlacement();
};

const placementsProgress = computed(() => {
	const total = props.state.round?.players_count ?? 0;
	if (total <= 0) {
		return 0;
	}

	const placed = props.state.round?.placements_count ?? 0;

	return Math.min(100, Math.max(0, Math.round((placed / total) * 100)));
});

const totalPlacementTime = computed(() => {
	const unlocks = props.state.round?.placement_unlocks_at;
	const ends = props.state.round?.placement_ends_at;
	if (!unlocks || !ends) {
		return 0;
	}

	const ms = new Date(ends).getTime() - new Date(unlocks).getTime();

	return Math.max(0, Math.ceil(ms / 1000));
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
                            class="p-6 bg-linear-to-br from-accent-500/20 to-accent-600/20 border border-accent-500/40"
                        >
                            <p class="text-center text-accent-200 font-medium">
                                Waiting for reveal...
                            </p>
                        </GlassCard>
                    </template>
                </div>

                <!-- Unified Timeline -->
                <GlassCard class="p-6">
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <h3 class="text-xl font-bold font-display">Timeline</h3>
                        <div class="text-xs text-white/60 font-mono-party">
                            {{ state.round.placements_count }} / {{ state.round.players_count }} placed
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="h-2 rounded bg-white/10 overflow-hidden mb-4">
                        <div class="h-full bg-accent-500/70 transition-all duration-300" :style="{ width: `${placementsProgress}%` }" />
                    </div>

                    <!-- Placement timer (when active) -->
                    <div v-if="placementTimeRemaining > 0" class="flex items-center justify-center gap-2 mb-4">
                        <CountdownRing :remaining="placementTimeRemaining" :total="totalPlacementTime" />
                        <span class="text-sm font-mono-party" :class="placementTimeRemaining <= 5 ? 'text-red-400' : 'text-white/70'">
                            {{ placementTimeRemaining }}s
                        </span>
                    </div>

                    <!-- Time expired -->
                    <Transition name="feedback-slide">
                        <div
                            v-if="isPlacementTimeExpired"
                            class="flex items-center justify-center gap-4 p-4 bg-red-500/20 border border-red-500 rounded-lg mb-4"
                        >
                            <svg class="w-8 h-8 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <span class="font-medium text-red-200">Time's up!</span>
                        </div>
                    </Transition>

                    <!-- Error -->
                    <Transition name="feedback-slide">
                        <div v-if="placeError" class="p-4 bg-red-500/20 border border-red-500 rounded-lg text-center mb-4 animate-shake">
                            {{ placeError }}
                        </div>
                    </Transition>

                    <!-- Instruction text -->
                    <div class="text-sm text-white/65 mb-3">
                        <span v-if="isPlacementLocked && remainingSeconds > 0">Listen to the track...</span>
                        <span v-else-if="isPlacementTimeExpired">Waiting for reveal...</span>
                        <span v-else-if="!hasGuessInTimeline">Tap a slot to place the mystery track.</span>
                        <span v-else-if="!isPlacementLocked">Tap another slot to move, or undo.</span>
                        <span v-else>Placement locked.</span>
                    </div>

                    <!-- Timeline with interleaved drop zones -->
                    <div class="space-y-1">
                        <template
                            v-for="item in timelineWithDropZones"
                            :key="item.kind === 'track' ? `t-${item.track_id}` : `dz-${item.insertAfterTrackId}`"
                        >
                            <TimelineDropZone
                                v-if="item.kind === 'drop-zone'"
                                :insert-after-track-id="item.insertAfterTrackId"
                                :is-active="item.isActive"
                                :has-placed="hasGuessInTimeline"
                                :disabled="isPlacementLocked || isPlacementTimeExpired"
                                :is-placing="isPlacing || isUndoingPlacement"
                                @place="handleDropZonePlace"
                                @undo="handleUndo"
                            />

                            <!-- Track card (static) -->
                            <div
                                v-else
                                class="flex items-center gap-3 p-4 rounded-lg bg-white/5 border border-accent-500/15"
                            >
                                <img
                                    v-if="item.artwork_url"
                                    :src="item.artwork_url"
                                    alt=""
                                    class="w-14 h-14 rounded object-cover shrink-0"
                                />
                                <div v-else class="w-14 h-14 rounded bg-white/10 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <div class="text-[clamp(1.6rem,6vw,2.25rem)] font-black text-accent-400 leading-none mb-1">
                                        {{ item.year }}
                                    </div>
                                    <div class="font-medium truncate text-sm">{{ item.title }}</div>
                                    <div class="text-xs text-white/60 truncate">{{ item.artist }}</div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Empty state hint -->
                    <div v-if="(state.timeline?.length ?? 0) === 0 && !hasGuessInTimeline" class="text-center text-white/55 mt-3">
                        Tap the slot above to place as your first track
                    </div>
                </GlassCard>

                <GlassCard class="p-4 hidden lg:block">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="text-sm text-white/60 font-mono-party">
                            {{ state.round.placements_count }} / {{ state.round.players_count }} players have placed
                        </div>
                        <button
                            v-if="isHost"
                            type="button"
                            class="btn-interactive btn-warning px-6 py-2 rounded-lg font-bold disabled:cursor-not-allowed"
                            :disabled="state.round.players_count === 0"
                            @click="revealRound"
                        >
                            Reveal Answers
                        </button>
                        <div v-else class="text-sm text-white/60">Waiting for host to reveal...</div>
                    </div>
                </GlassCard>
            </div>

            <div class="lg:col-span-3 lg:sticky lg:top-6 space-y-6">
                <GlassCard class="p-8">
                    <h2 class="text-xl font-bold mb-4 font-display">Players</h2>
                    <PlayersList :players="state.players" show-score />
                </GlassCard>
            </div>
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
                    type="button"
                    class="btn-interactive btn-secondary min-h-11 px-4 py-3 rounded-xl font-bold text-white"
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
                    class="btn-interactive btn-warning col-span-2 min-h-11 px-4 py-3 rounded-xl font-bold"
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
