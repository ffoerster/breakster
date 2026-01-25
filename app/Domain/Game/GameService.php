<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\DTOs\PlacementResult;
use App\Domain\Game\DTOs\RoundSummary;
use App\Domain\Game\Enums\RoomState;
use App\Domain\Game\Enums\RoundStatus;
use App\Events\PlacementSubmitted;
use App\Events\RoomPlayersUpdated;
use App\Events\RoomStateChanged;
use App\Events\RoundRevealed;
use App\Events\RoundStarted;
use App\Models\Pack;
use App\Models\Placement;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\RoomUsedTrack;
use App\Models\Round;
use App\Models\TimelineItem;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameService
{
    public function __construct(
        private readonly RoomCodeGenerator $codeGenerator,
        private readonly PlacementValidator $placementValidator,
        private readonly ScoreCalculator $scoreCalculator,
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
            $displayName = $host?->name ?? $guestDisplayName ?? 'Host';

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

        // Check if room is joinable
        if ($room->state === RoomState::Finished) {
            throw new \RuntimeException('This room has already finished.');
        }

        // Check for existing player
        $existingPlayer = $this->findExistingPlayer($room, $user);
        if ($existingPlayer) {
            return $existingPlayer;
        }

        // Generate unique display name
        $displayName = $this->ensureUniqueDisplayName($room, $guestDisplayName ?? $user?->name ?? 'Player');

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

        $this->ensureSeedCards($room);

        // Get available tracks
        $usedTrackIds = $room->usedTrackIds();
        $track = $room->pack->activeTracks()->whereNotIn('id', $usedTrackIds)->inRandomOrder()->first();

        if (! $track) {
            // No more tracks available
            $room->update(['state' => RoomState::Finished]);

            throw new \RuntimeException('No more tracks available.');
        }

        return DB::transaction(function () use ($room, $track) {
            // Calculate round number
            $roundNumber = $room->rounds()->count() + 1;

            RoomUsedTrack::firstOrCreate([
                'room_id' => $room->id,
                'track_id' => $track->id,
            ]);

            $placementDelaySeconds = 3;
            $startedAt = now();

            $round = Round::create([
                'room_id' => $room->id,
                'track_id' => $track->id,
                'round_number' => $roundNumber,
                'status' => RoundStatus::Active,
                'started_at' => $startedAt,
                'placement_unlocks_at' => $startedAt->copy()->addSeconds($placementDelaySeconds),
            ]);

            $room->update([
                'state' => RoomState::InRound,
                'current_round_id' => $round->id,
            ]);

            // Broadcast events after transaction commits
            DB::afterCommit(function () use ($room, $round) {
                event(new RoundStarted(
                    $room->code,
                    $round->id,
                    $round->round_number,
                    $round->placement_unlocks_at
                ));
                event(new RoomStateChanged($room->code, RoomState::InRound, $round->id));
            });

            return $round;
        });
    }

    public function ensureSeedCards(Room $room): void
    {
        $alreadySeeded = $room->players()->whereHas('timelineItems')->exists();

        if ($alreadySeeded) {
            return;
        }

        DB::transaction(function () use ($room): void {
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

    /**
     * Submit a placement for the current round.
     */
    public function submitPlacement(RoomPlayer $player, Round $round, ?int $insertAfterTrackId = null): Placement
    {
        // Validate round is active
        if (! $round->isActive()) {
            throw new \RuntimeException('This round is not active.');
        }

        // Check if player already placed
        $existingPlacement = $round->getPlacementForPlayer($player);
        if ($existingPlacement) {
            throw new \RuntimeException('You have already submitted a placement for this round.');
        }

        // Validate insertAfterTrackId is in player's timeline (if not null)
        if ($insertAfterTrackId !== null) {
            $hasTrack = $player->timelineItems()->where('track_id', $insertAfterTrackId)->exists();

            if (! $hasTrack) {
                throw new \RuntimeException('Invalid track reference for placement.');
            }
        }

        $placement = Placement::create([
            'round_id' => $round->id,
            'room_player_id' => $player->id,
            'inserted_after_track_id' => $insertAfterTrackId,
            'is_correct' => null, // Will be set during reveal
            'points_earned' => 0,
        ]);

        // Broadcast placement submitted
        event(new PlacementSubmitted($player->room->code, $round->id, $player->id));

        return $placement;
    }

    /**
     * Check if all players have submitted placements.
     */
    public function allPlayersSubmitted(Round $round): bool
    {
        return $round->allPlayersSubmitted();
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

                // Calculate points
                $secondsToSubmit = $round->started_at ? $placement->created_at->diffInSeconds($round->started_at) : 0;
                $points = $this->scoreCalculator->calculatePoints($isCorrect, (int) $secondsToSubmit);

                // Update placement
                $placement->update([
                    'is_correct' => $isCorrect,
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
            DB::afterCommit(function () use ($room, $round) {
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

        return $players->map(function (RoomPlayer $player) use (&$rank, &$lastScore, &$sameRankCount) {
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
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        DB::transaction(function () use ($player, $orderedTrackIds): void {
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

        return ! $room->pack->activeTracks()->whereNotIn('id', $usedTrackIds)->exists();
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
