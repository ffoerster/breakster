<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\DTOs\PlacementResult;
use App\Domain\Game\DTOs\RoundSummary;
use App\Domain\Game\Enums\RoomState;
use App\Domain\Game\Enums\RoundStatus;
use App\Domain\Providers\Spotify\SpotifyPlaybackService;
use App\Events\AmenClaimed;
use App\Events\AmenGuessSubmitted;
use App\Events\AmenResolved;
use App\Events\AmenVoteSubmitted;
use App\Events\PlacementSubmitted;
use App\Events\PlaybackFailed;
use App\Events\PlayerReady;
use App\Events\RoomPlayersUpdated;
use App\Events\RoomStateChanged;
use App\Events\RoundRevealed;
use App\Events\RoundStarted;
use App\Jobs\ResolvePlacementTimeoutJob;
use App\Models\AmenGuess;
use App\Models\AmenVote;
use App\Models\Pack;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\RoomUsedTrack;
use App\Models\Round;
use App\Models\TimelineItem;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameService
{
    public function __construct(
        private readonly RoomCodeGenerator $codeGenerator,
        private readonly PlacementValidator $placementValidator,
        private readonly ScoreCalculator $scoreCalculator,
        private readonly ?SpotifyPlaybackService $spotifyPlayback = null,
    ) {}

    /**
     * Create a new room with the given pack and host.
     */
    public function createRoom(Pack $pack, ?User $host = null, ?string $guestDisplayName = null): Room
    {
        return DB::transaction(function () use ($pack, $host, $guestDisplayName) {
            $room = Room::create([
                'code' => $this->codeGenerator->generate(),
                'host_user_id' => $host?->id,
                'pack_id' => $pack->id,
                'state' => RoomState::Lobby,
            ]);

            // Create host player
            $displayName = $host->name ?? $guestDisplayName ?? 'Host';

            RoomPlayer::create([
                'room_id' => $room->id,
                'user_id' => $host?->id,
                'display_name' => $displayName,
                'session_id' => $host ? null : session()->getId(),
                'is_host' => true,
                'joined_at' => now(),
            ]);

            return $room;
        });
    }

    /**
     * Join an existing room by code.
     */
    public function joinRoom(string $code, ?User $user = null, ?string $guestDisplayName = null): RoomPlayer
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        // Check for existing player
        $existingPlayer = $this->findExistingPlayer($room, $user);
        if ($existingPlayer) {
            return $existingPlayer;
        }

        // Check if room is joinable
        if ($room->state === RoomState::Finished) {
            throw new \RuntimeException('This room has already finished.');
        }

        if ($room->state !== RoomState::Lobby) {
            throw new \RuntimeException('This room has already started.');
        }

        // Generate unique display name
        $displayName = $this->ensureUniqueDisplayName($room, $guestDisplayName ?? $user->name ?? 'Player');

        $player = RoomPlayer::create([
            'room_id' => $room->id,
            'user_id' => $user?->id,
            'display_name' => $displayName,
            'session_id' => $user ? null : session()->getId(),
            'is_host' => false,
            'joined_at' => now(),
        ]);

        // Broadcast player joined
        event(new RoomPlayersUpdated($room->code));

        return $player;
    }

    public function finishRoom(Room $room): void
    {
        DB::transaction(static function () use ($room): void {
            $room->loadMissing('currentRound');

            if ($room->currentRound) {
                $room->currentRound->update([
                    'status' => RoundStatus::Done,
                ]);
            }

            $room->update([
                'state' => RoomState::Finished,
                'current_round_id' => null,
            ]);

            DB::afterCommit(static function () use ($room): void {
                event(new RoomStateChanged($room->code, RoomState::Finished, null));
            });
        });
    }

    /**
     * Leave a room.
     */
    public function leaveRoom(RoomPlayer $player): void
    {
        $room = $player->room;
        $roomCode = $room->code;
        $wasHost = $player->is_host;

        $player->delete();

        if ($wasHost) {
            // Transfer host to next player by join time
            $newHost = $room->players()->orderBy('joined_at')->first();

            if ($newHost) {
                $newHost->update(['is_host' => true]);
            } else {
                // No players left, close room
                $room->update(['state' => RoomState::Finished]);
            }
        }

        // Broadcast player left
        event(new RoomPlayersUpdated($roomCode));
    }

    /**
     * Start a new round in the room.
     */
    public function startRound(Room $room): Round
    {
        // Validate state
        if (! $room->state->canStartRound()) {
            throw new \RuntimeException('Cannot start a round in the current state.');
        }

        // Require playback provider to be selected
        if (! $room->playback_provider_id) {
            throw new \DomainException('Playback provider not selected.');
        }

        $this->ensureSeedCards($room);

        // Get available tracks
        $usedTrackIds = $room->usedTrackIds();
        $track = $room
            ->pack
            ->activeTracks()
            ->where('provider_id', $room->playback_provider_id)
            ->whereNotIn('id', $usedTrackIds)
            ->inRandomOrder()
            ->first();

        if (! $track) {
            // No more tracks available
            $room->update(['state' => RoomState::Finished]);

            throw new \RuntimeException('No more tracks available.');
        }

        return DB::transaction(function () use ($room, $track) {
            // Reset ready status for all players
            $this->resetReadyStatus($room);

            // Calculate round number
            $roundNumber = $room->rounds()->count() + 1;

            RoomUsedTrack::firstOrCreate([
                'room_id' => $room->id,
                'track_id' => $track->id,
            ]);

            $placementDelaySeconds = config('game.placement_delay', 3);
            $startedAt = now();
            $placementUnlocksAt = $startedAt->copy()->addSeconds($placementDelaySeconds);

            // Only set placement end time if timer is enabled
            $placementEndsAt = null;
            if ($room->placement_timer_enabled) {
                $placementDurationSeconds = config('game.placement_duration', 12);
                $placementEndsAt = $placementUnlocksAt->copy()->addSeconds($placementDurationSeconds);
            }

            $round = Round::create([
                'room_id' => $room->id,
                'track_id' => $track->id,
                'round_number' => $roundNumber,
                'status' => RoundStatus::Active,
                'started_at' => $startedAt,
                'placement_unlocks_at' => $placementUnlocksAt,
                'placement_ends_at' => $placementEndsAt,
            ]);

            $room->update([
                'state' => RoomState::InRound,
                'current_round_id' => $round->id,
            ]);

            // Broadcast events after transaction commits
            DB::afterCommit(function () use ($room, $round, $track) {
                event(new RoundStarted(
                    $room->code,
                    $round->id,
                    $round->round_number,
                    $round->placement_unlocks_at,
                    $round->placement_ends_at
                ));
                event(new RoomStateChanged($room->code, RoomState::InRound, $round->id));

                // Dispatch job to auto-place missing players at timeout (only if timer enabled)
                if ($round->placement_ends_at !== null) {
                    ResolvePlacementTimeoutJob::dispatch($round->id)
                        ->delay($round->placement_ends_at);
                }

                // Trigger Spotify playback if configured
                $this->triggerSpotifyPlayback($room, $track);
            });

            return $round;
        });
    }

    /**
     * Attempt to start Spotify playback for the round track.
     * Failures are logged but do not fail the round.
     */
    private function triggerSpotifyPlayback(Room $room, Track $track): void
    {
        // Only trigger if Spotify is the provider and device is configured
        if (! $room->playbackProvider || $room->playbackProvider->key !== 'spotify') {
            return;
        }

        if (! $room->spotify_device_id || ! $room->host_user_id) {
            return;
        }

        // Require Spotify playback service
        if (! $this->spotifyPlayback) {
            Log::warning('SpotifyPlaybackService not available for room', [
                'room_code' => $room->code,
            ]);

            return;
        }

        // Get Spotify track URI
        $spotifyTrackId = $track->provider_track_id;
        if (! $spotifyTrackId) {
            Log::info('Track has no Spotify provider_track_id, skipping auto-play', [
                'room_code' => $room->code,
                'track_id' => $track->id,
            ]);

            return;
        }

        $spotifyUri = "spotify:track:{$spotifyTrackId}";

        try {
            $room->load('host');
            $host = $room->host; // Can be null for guest hosts

            $this->spotifyPlayback->startPlayback($host, $room->spotify_device_id, $spotifyUri);

            Log::info('Spotify playback started successfully', [
                'room_code' => $room->code,
                'track_uri' => $spotifyUri,
                'device_id' => $room->spotify_device_id,
            ]);
        } catch (\Throwable $e) {
            // Log the error but don't fail the round
            Log::error('Failed to start Spotify playback', [
                'room_code' => $room->code,
                'error' => $e->getMessage(),
                'track_uri' => $spotifyUri,
            ]);

            event(new PlaybackFailed(
                roomCode: $room->code,
                providerKey: 'spotify',
                message: 'Spotify playback failed to start. Open Spotify on your device and try "Retry Spotify".',
            ));
        }
    }

    public function ensureSeedCards(Room $room): void
    {
        $alreadySeeded = $room->players()->whereHas('timelineItems')->exists();

        if ($alreadySeeded) {
            return;
        }

        DB::transaction(static function () use ($room): void {
            $room->refresh();

            $players = $room->players()->orderBy('joined_at')->get();
            if ($players->isEmpty()) {
                throw new \RuntimeException('Cannot seed cards without players.');
            }

            $usedTrackIds = $room->usedTrackIds();
            $tracks = $room
                ->pack
                ->activeTracks()
                ->whereNotIn('id', $usedTrackIds)
                ->inRandomOrder()
                ->limit($players->count())
                ->get();

            if ($tracks->count() < $players->count()) {
                throw new \RuntimeException('Not enough unused tracks to seed the game.');
            }

            foreach ($players as $index => $player) {
                $track = $tracks->get($index);
                if (! $track) {
                    continue;
                }

                TimelineItem::create([
                    'room_player_id' => $player->id,
                    'track_id' => $track->id,
                    'position' => 0,
                ]);

                RoomUsedTrack::firstOrCreate([
                    'room_id' => $room->id,
                    'track_id' => $track->id,
                ]);
            }
        });
    }

    public function setPlaybackProvider(Room $room, Provider $provider): void
    {
        if (! $room->isInLobby()) {
            throw new \RuntimeException('Playback provider can only be changed in the lobby.');
        }

        if (! $provider->is_active) {
            throw new \RuntimeException('Playback provider is not active.');
        }

        if (! in_array($provider->key, ['spotify', 'tidal'], true)) {
            throw new \RuntimeException('Playback provider is not supported.');
        }

        DB::transaction(static function () use ($room, $provider): void {
            $room->update([
                'playback_provider_id' => $provider->id,
            ]);

            DB::afterCommit(static function () use ($room): void {
                event(new RoomStateChanged($room->code, $room->state, $room->current_round_id));
            });
        });
    }

    public function setPlacementTimerEnabled(Room $room, bool $enabled): void
    {
        if (! $room->isInLobby()) {
            throw new \RuntimeException('Placement timer can only be changed in the lobby.');
        }

        DB::transaction(static function () use ($room, $enabled): void {
            $room->update([
                'placement_timer_enabled' => $enabled,
            ]);

            DB::afterCommit(static function () use ($room): void {
                event(new RoomStateChanged($room->code, $room->state, $room->current_round_id));
            });
        });
    }

    /**
     * Submit a placement for the current round.
     */
    public function submitPlacement(
        RoomPlayer $player,
        Round $round,
        ?int $insertAfterTrackId = null,
        bool $isAutoPlaced = false
    ): Placement {
        // Validate round is active
        if (! $round->isActive()) {
            throw new \RuntimeException('This round is not active.');
        }

        // Validate placement period hasn't expired (unless auto-placement)
        if (! $isAutoPlaced && $round->isPlacementPeriodExpired()) {
            throw new \RuntimeException('Placement time has expired.');
        }

        // Validate insertAfterTrackId is in player's timeline (if not null)
        if ($insertAfterTrackId !== null) {
            $hasTrack = $player->timelineItems()->where('track_id', $insertAfterTrackId)->exists();

            if (! $hasTrack) {
                throw new \RuntimeException('Invalid track reference for placement.');
            }
        }

        $placement = Placement::query()->firstOrNew([
            'round_id' => $round->id,
            'room_player_id' => $player->id,
        ]);

        $placement->fill([
            'inserted_after_track_id' => $insertAfterTrackId,
            'is_correct' => null, // Will be set during reveal
            'points_earned' => 0,
            'is_auto_placed' => $isAutoPlaced,
        ]);

        $placement->save();

        // Broadcast placement submitted
        event(new PlacementSubmitted($player->room->code, $round->id, $player->id));

        return $placement->fresh();
    }

    /**
     * Undo a placement for the current round.
     */
    public function undoPlacement(RoomPlayer $player, Round $round): void
    {
        if (! $round->isActive()) {
            throw new \RuntimeException('This round is not active.');
        }

        if ($round->isPlacementPeriodExpired()) {
            throw new \RuntimeException('Placement time has expired.');
        }

        $placement = Placement::query()
            ->where('round_id', $round->id)
            ->where('room_player_id', $player->id)
            ->first();

        if (! $placement) {
            throw new \RuntimeException('No placement to undo.');
        }

        $placement->delete();

        event(new PlacementSubmitted($player->room->code, $round->id, $player->id));
    }

    /**
     * Check if all players have submitted placements.
     */
    public function allPlayersSubmitted(Round $round): bool
    {
        return $round->allPlayersSubmitted();
    }

    /**
     * Auto-place all players who haven't submitted a placement for the round.
     * Places at the end of their timeline (after last track, or null if empty).
     *
     * @return int Number of players auto-placed
     */
    public function autoPlaceMissingPlayers(Round $round): int
    {
        if (! $round->isActive()) {
            return 0;
        }

        // Load room fresh from DB to ensure we have all players
        $room = Room::query()->find($round->room_id);

        if (! $room instanceof Room) {
            return 0;
        }

        $playersWithPlacements = Placement::query()
            ->where('round_id', $round->id)
            ->pluck('room_player_id');

        $missingPlayers = $room->players()
            ->whereNotIn('id', $playersWithPlacements)
            ->get();

        $count = 0;

        foreach ($missingPlayers as $player) {
            // Find the last track in player's timeline
            $lastTimelineItem = $player->timelineItems()
                ->orderByDesc('position')
                ->first();

            $insertAfterTrackId = $lastTimelineItem?->track_id;

            $this->submitPlacement($player, $round, $insertAfterTrackId, isAutoPlaced: true);
            $count++;
        }

        return $count;
    }

    /**
     * Reveal the round and calculate scores.
     */
    public function revealRound(Round $round): RoundSummary
    {
        $room = $round->room;
        $track = $round->track;

        return DB::transaction(function () use ($round, $room, $track) {
            $results = collect();

            foreach ($round->placements as $placement) {
                $player = $placement->player;

                // Check correctness
                $isCorrect = $this->placementValidator->isCorrect($player, $track, $placement->inserted_after_track_id);

                // Calculate year delta (how many years off the placement was)
                $yearDelta = $this->placementValidator->calculateYearDelta($player, $track, $placement->inserted_after_track_id);

                // Calculate points
                $secondsToSubmit = $round->started_at ? $placement->created_at->diffInSeconds($round->started_at) : 0;
                $points = $this->scoreCalculator->calculatePoints($isCorrect, (int) $secondsToSubmit);

                // Update placement
                $placement->update([
                    'is_correct' => $isCorrect,
                    'year_delta' => $yearDelta,
                    'points_earned' => $points,
                ]);

                // If correct, add to timeline and update score
                if ($isCorrect) {
                    $this->addToTimeline($player, $track);
                    $player->addScore($points);
                }

                $result = $isCorrect
                    ? PlacementResult::correct($placement->fresh(), $points)
                    : PlacementResult::incorrect($placement->fresh());

                $results->put($player->id, $result);
            }

            // Update round status
            $round->update([
                'status' => RoundStatus::Revealed,
                'revealed_at' => now(),
            ]);

            // Update room state
            $room->update(['state' => RoomState::Reveal]);

            // Broadcast events after transaction commits
            DB::afterCommit(static function () use ($room, $round) {
                event(new RoundRevealed($room->code, $round->id));
                event(new RoomStateChanged($room->code, RoomState::Reveal, $round->id));
            });

            return RoundSummary::fromRound($round->fresh(), $results);
        });
    }

    /**
     * Proceed to next round or finish game.
     */
    public function nextRound(Room $room): ?Round
    {
        // Mark current round as done
        if ($room->current_round_id) {
            $room->currentRound?->update(['status' => RoundStatus::Done]);
        }

        try {
            return $this->startRound($room);
        } catch (\RuntimeException $e) {
            // No more tracks - game is finished
            // Broadcast game finished
            event(new RoomStateChanged($room->code, RoomState::Finished, null));

            return null;
        }
    }

    /**
     * Get leaderboard for room.
     *
     * @return Collection<int, array{player: RoomPlayer, score: int, rank: int}>
     */
    public function getLeaderboard(Room $room): Collection
    {
        $players = $room->players()->orderByDesc('score')->orderBy('joined_at')->get();

        $rank = 0;
        $lastScore = null;
        $sameRankCount = 0;

        return $players->map(static function (RoomPlayer $player) use (&$rank, &$lastScore, &$sameRankCount) {
            if ($lastScore !== $player->score) {
                $rank += 1 + $sameRankCount;
                $sameRankCount = 0;
            } else {
                $sameRankCount++;
            }
            $lastScore = $player->score;

            return [
                'player' => $player,
                'score' => $player->score,
                'rank' => $rank,
            ];
        });
    }

    /**
     * @param  array<int, int|string>  $orderedTrackIds
     */
    public function reorderTimeline(RoomPlayer $player, array $orderedTrackIds): void
    {
        $orderedTrackIds = collect($orderedTrackIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        DB::transaction(static function () use ($player, $orderedTrackIds): void {
            $items = $player->timelineItems()->get()->keyBy('track_id');

            if (count($orderedTrackIds) !== $items->count()) {
                throw new \RuntimeException('Invalid timeline reorder request.');
            }

            foreach ($orderedTrackIds as $position => $trackId) {
                /** @var TimelineItem|null $item */
                $item = $items->get($trackId);

                if (! $item) {
                    throw new \RuntimeException('Invalid timeline reorder request.');
                }

                $item->update(['position' => $position]);
            }
        });
    }

    /**
     * Check if game should end (no more tracks available).
     */
    public function isGameComplete(Room $room): bool
    {
        $usedTrackIds = $room->usedTrackIds();

        return ! $room
            ->pack
            ->activeTracks()
            ->where('provider_id', $room->playback_provider_id)
            ->whereNotIn('id', $usedTrackIds)
            ->exists();
    }

    /**
     * Claim AMEN for the current round.
     * Only one player can claim per round, first-write-wins.
     */
    public function claimAmen(Round $round, RoomPlayer $player): Round
    {
        return DB::transaction(static function () use ($round, $player): Round {
            // Lock the round row to prevent race conditions
            $round = Round::query()->lockForUpdate()->find($round->id);

            if (! $round->isActive()) {
                throw new \RuntimeException('This round is not active.');
            }

            if ($round->hasAmenClaim()) {
                throw new \RuntimeException('AMEN has already been claimed for this round.');
            }

            // Require at least 2 players for AMEN (need voters)
            $playerCount = $round->room->players()->count();
            if ($playerCount < 2) {
                throw new \RuntimeException('AMEN requires at least 2 players.');
            }

            $round->update([
                'amen_claimed_by_room_player_id' => $player->id,
                'amen_claimed_at' => now(),
            ]);

            $roomCode = $round->room->code;
            $displayName = $player->display_name;

            DB::afterCommit(static function () use ($roomCode, $round, $player, $displayName): void {
                event(new AmenClaimed($roomCode, $round->id, $player->id, $displayName));
            });

            return $round->fresh();
        });
    }

    /**
     * Submit an AMEN guess (artist and title).
     * Only the claimant can submit a guess.
     */
    public function submitAmenGuess(Round $round, RoomPlayer $player, string $artist, string $title): AmenGuess
    {
        if ($round->amen_claimed_by_room_player_id !== $player->id) {
            throw new \RuntimeException('Only the AMEN claimant can submit a guess.');
        }

        if ($round->amenGuess()->exists()) {
            throw new \RuntimeException('An AMEN guess has already been submitted for this round.');
        }

        $guess = AmenGuess::create([
            'round_id' => $round->id,
            'room_player_id' => $player->id,
            'artist_guess' => $artist,
            'title_guess' => $title,
        ]);

        $roomCode = $round->room->code;

        event(new AmenGuessSubmitted($roomCode, $round->id));

        return $guess;
    }

    /**
     * Submit an AMEN vote.
     * Only non-claimants can vote, and only once per round.
     */
    public function submitAmenVote(Round $round, RoomPlayer $voter, bool $vote): AmenVote
    {
        if (! $round->isRevealed()) {
            throw new \RuntimeException('Amen voting is only allowed after reveal.');
        }

        if (! $round->hasAmenClaim()) {
            throw new \RuntimeException('No AMEN claim exists for this round.');
        }

        if (! $round->amenGuess()->exists()) {
            throw new \RuntimeException('The AMEN claimant has not submitted a guess yet.');
        }

        if ($round->amen_claimed_by_room_player_id === $voter->id) {
            throw new \RuntimeException('The AMEN claimant cannot vote.');
        }

        if ($voter->hasVotedForAmenInRound($round)) {
            throw new \RuntimeException('You have already voted for this round.');
        }

        $amenVote = AmenVote::create([
            'round_id' => $round->id,
            'voter_room_player_id' => $voter->id,
            'vote' => $vote,
        ]);

        $roomCode = $round->room->code;
        $voteCount = $round->amenVotes()->count();

        event(new AmenVoteSubmitted($roomCode, $round->id, $voteCount));

        // Check if all eligible voters have voted and auto-resolve
        $eligibleVoters = $round->room->players()->count() - 1; // Exclude claimant
        if ($voteCount >= $eligibleVoters) {
            $this->resolveAmen($round->fresh());
        }

        return $amenVote;
    }

    /**
     * Resolve the AMEN claim and award/penalize points.
     */
    public function resolveAmen(Round $round): Round
    {
        if (! $round->hasAmenClaim()) {
            throw new \RuntimeException('No AMEN claim exists for this round.');
        }

        if ($round->isAmenResolved()) {
            return $round;
        }

        return DB::transaction(static function () use ($round): Round {
            $round = Round::query()->lockForUpdate()->find($round->id);

            // Double-check not already resolved
            if ($round->isAmenResolved()) {
                return $round;
            }

            $yesVotes = $round->amenVotes()->where('vote', true)->count();
            $noVotes = $round->amenVotes()->where('vote', false)->count();
            $totalVotes = $yesVotes + $noVotes;

            // Determine points based on voting
            $points = 0;
            $wasCorrect = false;

            if ($totalVotes >= 2 && $yesVotes > $noVotes) {
                // Majority voted yes
                $points = ScoreCalculator::AMEN_BONUS_POINTS;
                $wasCorrect = true;
            } elseif ($totalVotes >= 2 && $noVotes > $yesVotes) {
                // Majority voted no
                $points = ScoreCalculator::AMEN_PENALTY_POINTS;
                $wasCorrect = false;
            }
            // Tie or insufficient votes = 0 points

            // Apply points to claimant
            $claimant = $round->amenClaimant;
            if ($claimant && $points !== 0) {
                $claimant->addScore($points);
            }

            $round->update([
                'amen_awarded_points' => $points,
                'amen_resolved_at' => now(),
            ]);

            $roomCode = $round->room->code;
            $guess = $round->amenGuess;
            $track = $round->track;

            DB::afterCommit(static function () use ($roomCode, $round, $wasCorrect, $points, $guess, $track): void {
                event(
                    new AmenResolved(
                        $roomCode,
                        $round->id,
                        $wasCorrect,
                        $points,
                        $guess ? ['artist' => $guess->artist_guess, 'title' => $guess->title_guess] : null,
                        $track ? ['artist' => $track->artist, 'title' => $track->title] : null,
                    ),
                );
            });

            return $round->fresh();
        });
    }

    /**
     * Mark a player as ready in the lobby.
     */
    public function markReady(Room $room, RoomPlayer $player): void
    {
        if (! $room->isInLobby()) {
            throw new \RuntimeException('Ready can only be marked in the lobby.');
        }

        if ($player->is_host) {
            return;
        }

        $player->markReady();

        $readyCount = $room->players()->where('is_host', false)->where('is_ready', true)->count();
        $totalPlayers = $room->players()->where('is_host', false)->count();
        $roomCode = $room->code;

        event(new PlayerReady($roomCode, $player->id, $readyCount, $totalPlayers));
    }

    /**
     * Reset ready status for all players in a room.
     */
    public function resetReadyStatus(Room $room): void
    {
        $room->players()->update(['is_ready' => false]);
    }

    /**
     * Find existing player in room for user or session.
     */
    private function findExistingPlayer(Room $room, ?User $user): ?RoomPlayer
    {
        if ($user) {
            return $room->players()->where('user_id', $user->id)->first();
        }

        return $room->players()->where('session_id', session()->getId())->first();
    }

    /**
     * Ensure display name is unique in the room.
     */
    private function ensureUniqueDisplayName(Room $room, string $baseName): string
    {
        $name = $baseName;
        $suffix = 1;

        while ($room->players()->where('display_name', $name)->exists()) {
            $suffix++;
            $name = "{$baseName} ({$suffix})";
        }

        return $name;
    }

    /**
     * Add a track to player's timeline at the correct position.
     */
    private function addToTimeline(RoomPlayer $player, Track $track): void
    {
        $correctPosition = $this->placementValidator->findCorrectPosition($player, $track);

        // Shift existing items at or after this position
        $player->timelineItems()->where('position', '>=', $correctPosition)->increment('position');

        // Insert the new item
        TimelineItem::create([
            'room_player_id' => $player->id,
            'track_id' => $track->id,
            'position' => $correctPosition,
        ]);
    }
}
