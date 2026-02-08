<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Enums\RoomState;
use App\Models\Provider;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Round;
use App\Models\TimelineItem;
use Illuminate\Support\Collection;

class RoomStateData
{
    public function __construct(
        private readonly RoomSession $roomSession,
    ) {}

    /**
     * @return array{
     *   room: array<string, mixed>,
     *   me: array<string, mixed>|null,
     *   players: array<int, array<string, mixed>>,
     *   providers: array<int, array{id: int, key: string, name: string}>,
     *   round: array<string, mixed>|null,
     *   timeline: array<int, array<string, mixed>>|null
     * }
     */
    public function forRoom(Room $room): array
    {
        $room->loadMissing([
            'pack',
            'playbackProvider',
            'players',
            'host.providerTokens',
            'currentRound.track',
            'currentRound.placements.player',
            'currentRound.placements.insertedAfterTrack',
            'currentRound.amenClaimant',
            'currentRound.amenGuess',
            'currentRound.amenVotes',
        ]);

        $me = $this->roomSession->currentPlayer($room);

        if (! $me) {
            return [
                'room' => $this->room($room, null),
                'me' => null,
                'players' => [],
                'providers' => $this->providers(),
                'round' => null,
                'timeline' => null,
            ];
        }

        return [
            'room' => $this->room($room, $me),
            'me' => $this->me($room, $me),
            'players' => $this->players($room, $me),
            'providers' => $this->providers(),
            'round' => $this->round($room, $me),
            'timeline' => $this->timeline($me),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function room(Room $room, ?RoomPlayer $me): array
    {
        $readyTotal = $room->players()->where('is_host', false)->count();

        return [
            'code' => $room->code,
            'state' => $room->state->value,
            'pack' => [
                'name' => $room->pack->name,
                'description' => $room->pack->description,
                'tracks_count' => $room->pack->tracks_count ?? null,
                'spotify_tracks_count' => $room->pack->spotify_tracks_count ?? null,
                'tidal_tracks_count' => $room->pack->tidal_tracks_count ?? null,
            ],
            'playback_provider' => $room->playbackProvider
                ? [
                    'id' => $room->playbackProvider->id,
                    'key' => $room->playbackProvider->key,
                    'name' => $room->playbackProvider->name,
                ] : null,
            'host_user_id' => $room->host_user_id,
            'spotify_device_id' => $room->spotify_device_id,
            'host_has_spotify_token' => $this->hostHasSpotifyToken($room),
            'is_host' => (bool) ($me->is_host ?? false),
            'ready_count' => $room->players()->where('is_host', false)->where('is_ready', true)->count(),
            'ready_total' => $readyTotal,
            'players_count' => $room->players()->count(),
            'placement_timer_enabled' => $room->placement_timer_enabled,
            'placement_timer_duration' => $room->placement_timer_duration ?? (int) config('game.placement_duration'),
            'placement_delay_seconds' => $room->placement_delay_seconds ?? (int) config('game.placement_delay'),
            'max_rounds' => $room->max_rounds,
            'amen_enabled' => $room->amen_enabled ?? true,
        ];
    }

    private function hostHasSpotifyToken(Room $room): bool
    {
        // Check database for authenticated hosts
        if ($room->host_user_id && $room->host) {
            return $room->host->providerTokens()->where('provider_key', 'spotify')->exists();
        }

        // Check session for guest hosts
        return session()->has('spotify.guest_token');
    }

    /**
     * @return array<string, mixed>
     */
    private function me(Room $room, RoomPlayer $me): array
    {
        $placementInsertedAfterTrackId = null;

        if ($room->currentRound) {
            $placementInsertedAfterTrackId = $room->currentRound->getPlacementForPlayer($me)?->inserted_after_track_id;
        }

        return [
            'id' => $me->id,
            'display_name' => $me->display_name,
            'score' => $me->score,
            'is_host' => $me->is_host,
            'has_placed' => $room->currentRound ? $me->hasPlacementForRound($room->currentRound) : false,
            'placement_inserted_after_track_id' => $placementInsertedAfterTrackId,
            'is_ready' => $me->is_ready,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function players(Room $room, ?RoomPlayer $me): array
    {
        return $room
            ->players
            ->sortBy('joined_at')
            ->values()
            ->map(static fn (RoomPlayer $player): array => [
                'id' => $player->id,
                'display_name' => $player->display_name,
                'score' => $player->score,
                'is_host' => $player->is_host,
                'is_me' => (bool) ($me && $player->id === $me->id),
                'is_ready' => $player->is_ready,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, key: string, name: string}>
     */
    private function providers(): array
    {
        return Provider::query()
            ->whereIn('key', ['spotify', 'tidal'])
            ->where('is_active', true)
            ->get()
            ->map(static fn (Provider $provider): array => [
                'id' => $provider->id,
                'key' => $provider->key,
                'name' => $provider->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function round(Room $room, ?RoomPlayer $me): ?array
    {
        if (! $room->currentRound) {
            return null;
        }

        $round = $room->currentRound;

        $base = [
            'id' => $round->id,
            'round_number' => $round->round_number,
            'status' => $round->status->value,
            'is_revealed' => $round->isRevealed(),
            'started_at' => $round->started_at?->toIso8601String(),
            'placement_unlocks_at' => $round->placement_unlocks_at?->toIso8601String(),
            'placement_ends_at' => $round->placement_ends_at?->toIso8601String(),
            'revealed_at' => $round->revealed_at?->toIso8601String(),
            'placements_count' => $round->placements()->count(),
            'players_count' => $room->players()->count(),
        ];

        $provider = $room->playbackProvider;
        $track = $round->track;

        if ($provider && $track) {
            $base['playback'] = [
                'provider_key' => $provider->key,
                'provider_name' => $provider->name,
                'preview_url' => $track->preview_url,
                'external_url' => $track->playbackUrlForProvider($provider),
            ];
        } else {
            $base['playback'] = null;
        }

        if ($room->state === RoomState::Reveal || $round->isRevealed()) {
            $base['track'] = [
                'id' => $track?->id,
                'year' => $track?->release_date?->format('Y'),
                'title' => $track?->title,
                'artist' => $track?->artist,
                'artwork_url' => $track?->artwork_url,
            ];

            $base['placements'] = $round
                ->placements
                ->map(static fn ($placement): array => [
                    'id' => $placement->id,
                    'player' => [
                        'id' => $placement->player->id,
                        'display_name' => $placement->player->display_name,
                    ],
                    'is_correct' => $placement->is_correct,
                    'points_earned' => $placement->points_earned,
                    'inserted_after_track_id' => $placement->inserted_after_track_id,
                    'inserted_after_year' => $placement->insertedAfterTrack?->release_date?->format('Y'),
                    'is_auto_placed' => $placement->is_auto_placed,
                ])
                ->values()
                ->all();
        } else {
            $base['track'] = null;
            $base['placements'] = null;
        }

        // Add AMEN state
        $base['amen'] = $this->amenState($round, $me, $room->players()->count(), $room->amen_enabled ?? true);

        return $base;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeline(RoomPlayer $me): array
    {
        /** @var Collection<int, TimelineItem> $items */
        $items = $me->timelineItems()->with('track')->orderBy('position')->get();

        return $items->map(static fn (TimelineItem $item): array => [
            'track_id' => $item->track_id,
            'position' => $item->position,
            'year' => $item->track->release_date->format('Y'),
            'title' => $item->track->title,
            'artist' => $item->track->artist,
            'artwork_url' => $item->track->artwork_url,
        ])->all();
    }

    /**
     * Build AMEN state for the current round.
     *
     * @return array<string, mixed>
     */
    private function amenState(Round $round, ?RoomPlayer $me, int $playerCount, bool $amenEnabled = true): array
    {
        $isResolved = $round->isAmenResolved();
        $hasClaim = $round->hasAmenClaim();
        $claimant = $round->amenClaimant;
        $guess = $round->amenGuess;
        $isClaimant = $me && $round->amen_claimed_by_room_player_id === $me->id;

        $votingOpen = $round->isRevealed() && $hasClaim && ! $isResolved;

        // Count votes
        $yesVotes = $round->amenVotes->where('vote', true)->count();
        $noVotes = $round->amenVotes->where('vote', false)->count();

        // Determine if current player can vote
        $canVote = false;
        if ($votingOpen && $guess && $me && ! $isClaimant) {
            $canVote = ! $me->hasVotedForAmenInRound($round);
        }

        // Get current player's vote (if any)
        $myVote = null;
        if ($me && $hasClaim) {
            $vote = $round->amenVotes->firstWhere('voter_room_player_id', $me->id);
            $myVote = $vote?->vote;
        }

        // Build guess data - only show to claimant until resolved
        $guessData = null;
        if ($guess && ($isClaimant || $isResolved || $votingOpen)) {
            $guessData = [
                'artist' => $guess->artist_guess,
                'title' => $guess->title_guess,
            ];
        }

        // Build actual track data - only after resolved
        $actualData = null;
        if ($isResolved && $round->track) {
            $actualData = [
                'artist' => $round->track->artist,
                'title' => $round->track->title,
            ];
        }

        return [
            'claimed_by' => $round->amen_claimed_by_room_player_id,
            'claimant_name' => $claimant?->display_name,
            'is_claimant' => $isClaimant,
            'has_guess' => $guess !== null,
            'guess' => $guessData,
            'actual' => $actualData,
            'votes' => [
                'yes' => $yesVotes,
                'no' => $noVotes,
            ],
            'my_vote' => $myVote,
            'resolved' => $isResolved,
            'correct' => $isResolved ? $round->amen_awarded_points > 0 : null,
            'awarded_points' => $round->amen_awarded_points,
            'can_claim' => $amenEnabled && $round->canClaimAmen() && $playerCount >= 2,
            'voting_open' => $votingOpen,
            'can_vote' => $canVote,
        ];
    }
}
