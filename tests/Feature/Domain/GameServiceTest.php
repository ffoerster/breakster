<?php

use App\Domain\Game\Enums\RoomState;
use App\Domain\Game\Enums\RoundStatus;
use App\Domain\Game\GameService;
use App\Models\Pack;
use App\Models\Placement;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Round;
use App\Models\TimelineItem;
use App\Models\Track;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->gameService = app(GameService::class);
    $this->pack = Pack::factory()->create();
    Track::factory()->count(20)->for($this->pack)->create();
});

describe('GameService::createRoom', function () {
    it('creates a room with unique code', function () {
        $room = $this->gameService->createRoom($this->pack);

        expect($room)->toBeInstanceOf(Room::class)
            ->and($room->code)->toHaveLength(6)
            ->and($room->state)->toBe(RoomState::Lobby);
    });

    it('creates host player when creating room', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'TestHost');

        expect($room->players)->toHaveCount(1);
        expect($room->players->first()->is_host)->toBeTrue();
        expect($room->players->first()->display_name)->toBe('TestHost');
    });

    it('links room to user when provided', function () {
        $user = User::factory()->create();
        $room = $this->gameService->createRoom($this->pack, host: $user);

        expect($room->host_user_id)->toBe($user->id);
        expect($room->players->first()->user_id)->toBe($user->id);
    });
});

describe('GameService::joinRoom', function () {
    it('joins an existing room', function () {
        $hostUser = User::factory()->create();
        $playerUser = User::factory()->create();
        $room = $this->gameService->createRoom($this->pack, host: $hostUser);

        $player = $this->gameService->joinRoom($room->code, user: $playerUser);

        expect($player->room_id)->toBe($room->id)
            ->and($player->is_host)->toBeFalse()
            ->and($room->fresh()->players)->toHaveCount(2);
    });

    it('returns existing player if already joined', function () {
        $hostUser = User::factory()->create();
        $playerUser = User::factory()->create();
        $room = $this->gameService->createRoom($this->pack, host: $hostUser);

        $player1 = $this->gameService->joinRoom($room->code, user: $playerUser);
        $player2 = $this->gameService->joinRoom($room->code, user: $playerUser);

        expect($player1->id)->toBe($player2->id);
    });

    it('throws exception for finished room', function () {
        $hostUser = User::factory()->create();
        $playerUser = User::factory()->create();
        $room = $this->gameService->createRoom($this->pack, host: $hostUser);
        $room->update(['state' => RoomState::Finished]);

        expect(fn () => $this->gameService->joinRoom($room->code, user: $playerUser))
            ->toThrow(\RuntimeException::class, 'This room has already finished.');
    });

    it('generates unique display names with suffix', function () {
        $hostUser = User::factory()->create(['name' => 'Player']);
        $playerUser = User::factory()->create(['name' => 'Player']);
        $room = $this->gameService->createRoom($this->pack, host: $hostUser);

        $player2 = $this->gameService->joinRoom($room->code, user: $playerUser);

        expect($player2->display_name)->toBe('Player (2)');
    });
});

describe('GameService::startRound', function () {
    it('starts a new round', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'Host');

        $round = $this->gameService->startRound($room);

        expect($round)->toBeInstanceOf(Round::class)
            ->and($round->status)->toBe(RoundStatus::Active)
            ->and($round->started_at)->not->toBeNull()
            ->and($room->fresh()->state)->toBe(RoomState::InRound)
            ->and($room->fresh()->current_round_id)->toBe($round->id);
    });

    it('never repeats a track within a room', function () {
        $hostUser = User::factory()->create();
        $room = $this->gameService->createRoom($this->pack, host: $hostUser);
        $usedTrackIds = [];

        // Play multiple rounds
        for ($i = 0; $i < 10; $i++) {
            $room = $room->fresh();
            $round = $this->gameService->startRound($room);
            expect($usedTrackIds)->not->toContain($round->track_id);
            $usedTrackIds[] = $round->track_id;

            // Reveal to enable next round
            $round->update(['status' => RoundStatus::Revealed]);
            $room->refresh();
            $room->update(['state' => RoomState::Reveal]);
        }
    });

    it('throws exception when not in valid state', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'Host');
        $room->update(['state' => RoomState::InRound]);

        expect(fn () => $this->gameService->startRound($room))
            ->toThrow(\RuntimeException::class, 'Cannot start a round in the current state.');
    });

    it('finishes game when no tracks remain', function () {
        // Create pack with only 1 track
        $smallPack = Pack::factory()->create();
        Track::factory()->for($smallPack)->create();

        $room = $this->gameService->createRoom($smallPack, guestDisplayName: 'Host');

        // Play the only track
        $round = $this->gameService->startRound($room);
        $round->update(['status' => RoundStatus::Revealed]);
        $room->update(['state' => RoomState::Reveal]);

        // Try to start another round
        expect(fn () => $this->gameService->startRound($room->fresh()))
            ->toThrow(\RuntimeException::class, 'No more tracks available.');

        expect($room->fresh()->state)->toBe(RoomState::Finished);
    });
});

describe('GameService::submitPlacement', function () {
    it('creates a placement', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'Host');
        $player = $room->players->first();
        $round = $this->gameService->startRound($room);

        $placement = $this->gameService->submitPlacement($player, $round, null);

        expect($placement)->toBeInstanceOf(Placement::class)
            ->and($placement->room_player_id)->toBe($player->id)
            ->and($placement->round_id)->toBe($round->id);
    });

    it('throws exception for duplicate placement', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'Host');
        $player = $room->players->first();
        $round = $this->gameService->startRound($room);

        $this->gameService->submitPlacement($player, $round, null);

        expect(fn () => $this->gameService->submitPlacement($player, $round, null))
            ->toThrow(\RuntimeException::class, 'You have already submitted a placement for this round.');
    });

    it('throws exception for inactive round', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'Host');
        $player = $room->players->first();
        $round = $this->gameService->startRound($room);
        $round->update(['status' => RoundStatus::Revealed]);

        expect(fn () => $this->gameService->submitPlacement($player, $round, null))
            ->toThrow(\RuntimeException::class, 'This round is not active.');
    });
});

describe('GameService::revealRound', function () {
    it('calculates correctness and awards points for correct placement', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'Host');
        $player = $room->players->first();

        // Create a track with a known date
        $track = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1995-01-01'),
        ]);

        // Create round with this specific track
        $round = Round::factory()->active()->create([
            'room_id' => $room->id,
            'track_id' => $track->id,
        ]);
        $room->update(['state' => RoomState::InRound, 'current_round_id' => $round->id]);

        // Submit placement at beginning (empty timeline = always correct)
        $this->gameService->submitPlacement($player, $round, null);

        $summary = $this->gameService->revealRound($round);

        expect($summary->correctCount)->toBe(1)
            ->and($summary->incorrectCount)->toBe(0);

        $result = $summary->getResultForPlayer($player->id);
        expect($result->isCorrect)->toBeTrue()
            ->and($result->pointsEarned)->toBeGreaterThan(0);

        // Check timeline was updated
        expect($player->fresh()->timelineItems)->toHaveCount(1);
    });

    it('does not add to timeline or award points for incorrect placement', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'Host');
        $player = $room->players->first();

        // Add an existing track to timeline (1990)
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::create([
            'room_player_id' => $player->id,
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from 2000
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        $round = Round::factory()->active()->create([
            'room_id' => $room->id,
            'track_id' => $newTrack->id,
        ]);
        $room->update(['state' => RoomState::InRound, 'current_round_id' => $round->id]);

        // Submit placement at beginning (incorrect - 2000 should not come before 1990)
        $this->gameService->submitPlacement($player, $round, null);

        $summary = $this->gameService->revealRound($round);

        expect($summary->correctCount)->toBe(0)
            ->and($summary->incorrectCount)->toBe(1);

        // Timeline should still only have 1 item
        expect($player->fresh()->timelineItems)->toHaveCount(1);
        expect($player->fresh()->score)->toBe(0);
    });

    it('increments player score only on correct placement', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'Host');
        $player = $room->players->first();

        $initialScore = $player->score;

        // Create track and round
        $track = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        $round = Round::factory()->active()->create([
            'room_id' => $room->id,
            'track_id' => $track->id,
        ]);
        $room->update(['state' => RoomState::InRound, 'current_round_id' => $round->id]);

        // Submit correct placement (empty timeline)
        $this->gameService->submitPlacement($player, $round, null);
        $this->gameService->revealRound($round);

        expect($player->fresh()->score)->toBeGreaterThan($initialScore);
    });
});

describe('GameService::nextRound', function () {
    it('starts next round after reveal', function () {
        $room = $this->gameService->createRoom($this->pack, guestDisplayName: 'Host');
        $round1 = $this->gameService->startRound($room);
        $round1->update(['status' => RoundStatus::Revealed]);
        $room->update(['state' => RoomState::Reveal]);

        $round2 = $this->gameService->nextRound($room);

        expect($round2)->toBeInstanceOf(Round::class)
            ->and($round2->id)->not->toBe($round1->id)
            ->and($round1->fresh()->status)->toBe(RoundStatus::Done);
    });

    it('returns null and finishes game when no tracks remain', function () {
        // Create pack with only 1 track
        $smallPack = Pack::factory()->create();
        Track::factory()->for($smallPack)->create();

        $room = $this->gameService->createRoom($smallPack, guestDisplayName: 'Host');
        $round = $this->gameService->startRound($room);
        $round->update(['status' => RoundStatus::Revealed]);
        $room->update(['state' => RoomState::Reveal]);

        $result = $this->gameService->nextRound($room);

        expect($result)->toBeNull()
            ->and($room->fresh()->state)->toBe(RoomState::Finished);
    });
});

describe('GameService::getLeaderboard', function () {
    it('returns players sorted by score', function () {
        $hostUser = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $room = $this->gameService->createRoom($this->pack, host: $hostUser);
        $player1 = $room->players->first();
        $player1->update(['score' => 10]);

        $player2 = $this->gameService->joinRoom($room->code, user: $user2);
        $player2->update(['score' => 30]);

        $player3 = $this->gameService->joinRoom($room->code, user: $user3);
        $player3->update(['score' => 20]);

        $leaderboard = $this->gameService->getLeaderboard($room->fresh());

        expect($leaderboard)->toHaveCount(3)
            ->and($leaderboard[0]['player']->id)->toBe($player2->id)
            ->and($leaderboard[0]['rank'])->toBe(1)
            ->and($leaderboard[1]['player']->id)->toBe($player3->id)
            ->and($leaderboard[1]['rank'])->toBe(2)
            ->and($leaderboard[2]['player']->id)->toBe($player1->id)
            ->and($leaderboard[2]['rank'])->toBe(3);
    });

    it('handles tied scores with same rank', function () {
        $hostUser = User::factory()->create();
        $user2 = User::factory()->create();

        $room = $this->gameService->createRoom($this->pack, host: $hostUser);
        $player1 = $room->players->first();
        $player1->update(['score' => 20]);

        $player2 = $this->gameService->joinRoom($room->code, user: $user2);
        $player2->update(['score' => 20]);

        $leaderboard = $this->gameService->getLeaderboard($room->fresh());

        expect($leaderboard[0]['rank'])->toBe(1)
            ->and($leaderboard[1]['rank'])->toBe(1);
    });
});

describe('GameService::leaveRoom', function () {
    it('removes player from room', function () {
        $hostUser = User::factory()->create();
        $playerUser = User::factory()->create();

        $room = $this->gameService->createRoom($this->pack, host: $hostUser);
        $player = $this->gameService->joinRoom($room->code, user: $playerUser);

        $this->gameService->leaveRoom($player);

        expect($room->fresh()->players)->toHaveCount(1);
    });

    it('transfers host when host leaves', function () {
        $hostUser = User::factory()->create();
        $playerUser = User::factory()->create();

        $room = $this->gameService->createRoom($this->pack, host: $hostUser);
        $hostPlayer = $room->players->first();
        $player2 = $this->gameService->joinRoom($room->code, user: $playerUser);

        $this->gameService->leaveRoom($hostPlayer);

        expect($player2->fresh()->is_host)->toBeTrue();
    });

    it('finishes room when last player leaves', function () {
        $hostUser = User::factory()->create();
        $room = $this->gameService->createRoom($this->pack, host: $hostUser);
        $hostPlayer = $room->players->first();

        $this->gameService->leaveRoom($hostPlayer);

        expect($room->fresh()->state)->toBe(RoomState::Finished);
    });
});
