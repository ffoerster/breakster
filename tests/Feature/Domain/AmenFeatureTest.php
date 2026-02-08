<?php

use App\Domain\Game\Enums\RoomState;
use App\Domain\Game\Enums\RoundStatus;
use App\Domain\Game\GameService;
use App\Domain\Game\ScoreCalculator;
use App\Models\AmenGuess;
use App\Models\AmenVote;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Round;
use App\Models\Track;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->gameService = app(GameService::class);
    $this->provider = Provider::query()->firstOrCreate(['key' => 'tidal'], ['name' => 'TIDAL', 'is_active' => true]);
    $this->pack = Pack::factory()->create();
    Track::factory()
        ->count(20)
        ->for($this->pack)
        ->create();
});

/**
 * Helper to create a room with the playback provider set.
 */
function createRoomWithProvider($pack, $provider, $gameService, $guestDisplayName = 'Host'): Room
{
    $room = $gameService->createRoom($pack, guestDisplayName: $guestDisplayName);
    $room->update(['playback_provider_id' => $provider->id]);

    return $room->fresh();
}

describe('GameService::claimAmen', function () {
    it('allows a player to claim AMEN on an active round', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player = $room->players()->where('is_host', true)->first();

        Event::fake();

        $result = $this->gameService->claimAmen($round, $player);

        expect($result->amen_claimed_by_room_player_id)
            ->toBe($player->id)
            ->and($result->amen_claimed_at)
            ->not->toBeNull();

        Event::assertDispatched(\App\Events\AmenClaimed::class);
    });

    it('throws exception when AMEN is already claimed', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);

        expect(fn () => $this->gameService->claimAmen($round->fresh(), $player2))
            ->toThrow(\RuntimeException::class, 'AMEN has already been claimed for this round.');
    });

    it('throws exception when round is not active', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $round->update(['status' => RoundStatus::Revealed]);
        $player = $room->players()->where('is_host', true)->first();

        expect(fn () => $this->gameService->claimAmen($round->fresh(), $player))
            ->toThrow(\RuntimeException::class, 'This round is not active.');
    });

    it('throws exception when fewer than 2 players in room', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $round = $this->gameService->startRound($room);
        $player = $room->players()->where('is_host', true)->first();

        expect(fn () => $this->gameService->claimAmen($round, $player))
            ->toThrow(\RuntimeException::class, 'AMEN requires at least 2 players.');
    });
});

describe('GameService::submitAmenGuess', function () {
    it('allows claimant to submit a guess', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player);

        Event::fake();

        $guess = $this->gameService->submitAmenGuess($round->fresh(), $player, 'The Beatles', 'Hey Jude');

        expect($guess)
            ->toBeInstanceOf(AmenGuess::class)
            ->and($guess->artist_guess)
            ->toBe('The Beatles')
            ->and($guess->title_guess)
            ->toBe('Hey Jude')
            ->and($guess->room_player_id)
            ->toBe($player->id);

        Event::assertDispatched(\App\Events\AmenGuessSubmitted::class);
    });

    it('throws exception when non-claimant tries to submit', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);

        expect(fn () => $this->gameService->submitAmenGuess($round->fresh(), $player2, 'Artist', 'Title'))
            ->toThrow(\RuntimeException::class, 'Only the AMEN claimant can submit a guess.');
    });

    it('throws exception when guess already submitted', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player);
        $this->gameService->submitAmenGuess($round->fresh(), $player, 'Artist', 'Title');

        expect(fn () => $this->gameService->submitAmenGuess($round->fresh(), $player, 'Other', 'Song'))
            ->toThrow(\RuntimeException::class, 'An AMEN guess has already been submitted for this round.');
    });
});

describe('GameService::submitAmenVote', function () {
    it('throws exception when voting before reveal', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        expect(fn () => $this->gameService->submitAmenVote($round->fresh(), $player2, true))
            ->toThrow(\RuntimeException::class, 'Amen voting is only allowed after reveal.');
    });

    it('allows non-claimant to vote', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);
        $player3 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player3']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        Event::fake();

        $vote = $this->gameService->submitAmenVote($round->fresh(), $player2, true);

        expect($vote)
            ->toBeInstanceOf(AmenVote::class)
            ->and($vote->vote)
            ->toBeTrue()
            ->and($vote->voter_room_player_id)
            ->toBe($player2->id);

        Event::assertDispatched(\App\Events\AmenVoteSubmitted::class);
    });

    it('throws exception when claimant tries to vote', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        expect(fn () => $this->gameService->submitAmenVote($round->fresh(), $player1, true))
            ->toThrow(\RuntimeException::class, 'The AMEN claimant cannot vote.');
    });

    it('throws exception when player has already voted', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);
        RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player3']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        $this->gameService->submitAmenVote($round->fresh(), $player2, true);

        expect(fn () => $this->gameService->submitAmenVote($round->fresh(), $player2, false))
            ->toThrow(\RuntimeException::class, 'You have already voted for this round.');
    });

    it('throws exception when no AMEN claim exists', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        expect(fn () => $this->gameService->submitAmenVote($round, $player2, true))
            ->toThrow(\RuntimeException::class, 'No AMEN claim exists for this round.');
    });

    it('throws exception when no guess submitted yet', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        expect(fn () => $this->gameService->submitAmenVote($round->fresh(), $player2, true))
            ->toThrow(\RuntimeException::class, 'The AMEN claimant has not submitted a guess yet.');
    });

    it('auto-resolves when all eligible voters have voted', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        // Only player2 can vote (player1 is claimant)
        $this->gameService->submitAmenVote($round->fresh(), $player2, true);

        $round = $round->fresh();
        expect($round->isAmenResolved())->toBeTrue();
    });
});

describe('GameService::resolveAmen', function () {
    it('awards bonus points when majority votes yes', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);
        $player3 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player3']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();
        $initialScore = $player1->score;

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        // Both voters vote yes
        $this->gameService->submitAmenVote($round->fresh(), $player2, true);
        $this->gameService->submitAmenVote($round->fresh(), $player3, true);

        $round = $round->fresh();
        $player1 = $player1->fresh();

        expect($round->isAmenResolved())
            ->toBeTrue()
            ->and($round->amen_awarded_points)
            ->toBe(ScoreCalculator::AMEN_BONUS_POINTS)
            ->and($player1->score)
            ->toBe($initialScore + ScoreCalculator::AMEN_BONUS_POINTS);
    });

    it('applies penalty points when majority votes no', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);
        $player3 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player3']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();
        $player1->update(['score' => 5]); // Give some initial score
        $initialScore = $player1->fresh()->score;

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        // Both voters vote no
        $this->gameService->submitAmenVote($round->fresh(), $player2, false);
        $this->gameService->submitAmenVote($round->fresh(), $player3, false);

        $round = $round->fresh();
        $player1 = $player1->fresh();

        expect($round->isAmenResolved())
            ->toBeTrue()
            ->and($round->amen_awarded_points)
            ->toBe(ScoreCalculator::AMEN_PENALTY_POINTS)
            ->and($player1->score)
            ->toBe($initialScore + ScoreCalculator::AMEN_PENALTY_POINTS);
    });

    it('awards zero points on tie', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);
        $player3 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player3']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();
        $initialScore = $player1->score;

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        // One yes, one no = tie
        $this->gameService->submitAmenVote($round->fresh(), $player2, true);
        $this->gameService->submitAmenVote($round->fresh(), $player3, false);

        $round = $round->fresh();
        $player1 = $player1->fresh();

        expect($round->isAmenResolved())
            ->toBeTrue()
            ->and($round->amen_awarded_points)
            ->toBe(0)
            ->and($player1->score)
            ->toBe($initialScore);
    });

    it('allows a single eligible vote to decide in a 2-player room', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();
        $initialScore = $player1->score;

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        // Only one voter (player2), so their vote decides
        $this->gameService->submitAmenVote($round->fresh(), $player2, true);

        $round = $round->fresh();
        $player1 = $player1->fresh();

        // Only 1 vote, and it is a yes
        expect($round->isAmenResolved())
            ->toBeTrue()
            ->and($round->amen_awarded_points)
            ->toBe(ScoreCalculator::AMEN_BONUS_POINTS)
            ->and($player1->score)
            ->toBe($initialScore + ScoreCalculator::AMEN_BONUS_POINTS);
    });

    it('is idempotent - calling twice does not re-apply points', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);
        $player3 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player3']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);
        $this->gameService->submitAmenVote($round->fresh(), $player2, true);
        $this->gameService->submitAmenVote($round->fresh(), $player3, true);

        $scoreAfterFirstResolve = $player1->fresh()->score;

        // Attempt to resolve again
        $this->gameService->resolveAmen($round->fresh());

        expect($player1->fresh()->score)->toBe($scoreAfterFirstResolve);
    });
});

describe('AMEN integration with revealRound', function () {
    it('does not resolve AMEN during reveal', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);
        $player3 = RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player3']);

        $round = $this->gameService->startRound($room);
        $player1 = $room->players()->where('is_host', true)->first();

        $this->gameService->claimAmen($round, $player1);
        $this->gameService->submitAmenGuess($round->fresh(), $player1, 'Artist', 'Title');

        // Submit placements for reveal
        $this->gameService->submitPlacement($player1, $round, null);
        $this->gameService->submitPlacement($player2, $round->fresh(), null);
        $this->gameService->submitPlacement($player3, $round->fresh(), null);

        // Reveal without any votes submitted
        $this->gameService->revealRound($round->fresh());

        $round = $round->fresh();
        expect($round->isAmenResolved())->toBeFalse()->and($round->amen_awarded_points)->toBeNull();
    });

    it('does not fail reveal when AMEN has errors', function () {
        $room = createRoomWithProvider($this->pack, $this->provider, $this->gameService);
        RoomPlayer::factory()->create(['room_id' => $room->id, 'display_name' => 'Player2']);

        $round = $this->gameService->startRound($room);
        $player = $room->players()->where('is_host', true)->first();

        // Submit placement for reveal
        $this->gameService->submitPlacement($player, $round, null);

        // No AMEN claim - reveal should work
        $summary = $this->gameService->revealRound($round->fresh());

        expect($summary)->not->toBeNull();
    });
});

describe('AMEN API endpoints', function () {
    it('returns 409 when AMEN already claimed via API', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
            ]);
        $room->update(['current_round_id' => $round->id]);

        $host = RoomPlayer::factory()->asHost()->create(['room_id' => $room->id]);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id]);

        // First claim succeeds
        $round->update([
            'amen_claimed_by_room_player_id' => $host->id,
            'amen_claimed_at' => now(),
        ]);

        session()->put('room_player_id', $player2->id);

        $this->post("/room/{$room->code}/round/{$round->id}/amen")->assertStatus(409);
    });

    it('rejects AMEN vote via API before reveal', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
            ]);
        $room->update(['current_round_id' => $round->id]);

        $host = RoomPlayer::factory()->asHost()->create(['room_id' => $room->id]);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id]);

        $round->update([
            'amen_claimed_by_room_player_id' => $host->id,
            'amen_claimed_at' => now(),
        ]);
        AmenGuess::create([
            'round_id' => $round->id,
            'room_player_id' => $host->id,
            'artist_guess' => 'Test Artist',
            'title_guess' => 'Test Title',
        ]);

        session()->put('room_player_id', $player2->id);

        $this
            ->postJson("/room/{$room->code}/round/{$round->id}/amen/vote", [
                'vote' => true,
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Amen voting is only allowed after reveal.');
    });

    it('returns 409 when claimant tries to vote', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
            ]);
        $room->update(['current_round_id' => $round->id]);

        $host = RoomPlayer::factory()->asHost()->create(['room_id' => $room->id]);
        RoomPlayer::factory()->create(['room_id' => $room->id]);

        // Set up AMEN claim with guess
        $round->update([
            'amen_claimed_by_room_player_id' => $host->id,
            'amen_claimed_at' => now(),
        ]);
        AmenGuess::create([
            'round_id' => $round->id,
            'room_player_id' => $host->id,
            'artist_guess' => 'Test Artist',
            'title_guess' => 'Test Title',
        ]);

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        session()->put('room_player_id', $host->id);

        $this
            ->postJson("/room/{$room->code}/round/{$round->id}/amen/vote", [
                'vote' => true,
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'The AMEN claimant cannot vote.');
    });

    it('submits AMEN guess via API', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
            ]);
        $room->update(['current_round_id' => $round->id]);

        $host = RoomPlayer::factory()->asHost()->create(['room_id' => $room->id]);
        RoomPlayer::factory()->create(['room_id' => $room->id]);

        $round->update([
            'amen_claimed_by_room_player_id' => $host->id,
            'amen_claimed_at' => now(),
        ]);

        session()->put('room_player_id', $host->id);

        $this->post("/room/{$room->code}/round/{$round->id}/amen/guess", [
            'artist' => 'The Beatles',
            'title' => 'Hey Jude',
        ])->assertSuccessful();

        $this->assertDatabaseHas('amen_guesses', [
            'round_id' => $round->id,
            'room_player_id' => $host->id,
            'artist_guess' => 'The Beatles',
            'title_guess' => 'Hey Jude',
        ]);
    });

    it('submits AMEN vote via API', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
            ]);
        $room->update(['current_round_id' => $round->id]);

        $host = RoomPlayer::factory()->asHost()->create(['room_id' => $room->id]);
        $player2 = RoomPlayer::factory()->create(['room_id' => $room->id]);

        // Set up AMEN claim with guess
        $round->update([
            'amen_claimed_by_room_player_id' => $host->id,
            'amen_claimed_at' => now(),
        ]);
        AmenGuess::create([
            'round_id' => $round->id,
            'room_player_id' => $host->id,
            'artist_guess' => 'Test Artist',
            'title_guess' => 'Test Title',
        ]);

        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        session()->put('room_player_id', $player2->id);

        $this->postJson("/room/{$room->code}/round/{$round->id}/amen/vote", ['vote' => true])->assertSuccessful();

        $this->assertDatabaseHas('amen_votes', [
            'round_id' => $round->id,
            'voter_room_player_id' => $player2->id,
            'vote' => true,
        ]);
    });
});

describe('Room state payload AMEN voting gate', function () {
    it('exposes voting_open only after reveal and reveals guess to voters', function () {
        /** @var \Tests\TestCase $this */
        $pack = Pack::factory()->create();
        Track::factory()
            ->count(5)
            ->for($pack)
            ->create();

        $room = Room::factory()->inRound()->create(['pack_id' => $pack->id]);
        $track = $pack->tracks()->first();
        $round = Round::factory()
            ->active()
            ->create([
                'room_id' => $room->id,
                'track_id' => $track->id,
            ]);
        $room->update(['current_round_id' => $round->id]);

        $claimant = RoomPlayer::factory()->asHost()->create(['room_id' => $room->id]);
        $voter = RoomPlayer::factory()->create(['room_id' => $room->id]);

        $round->update([
            'amen_claimed_by_room_player_id' => $claimant->id,
            'amen_claimed_at' => now(),
        ]);
        AmenGuess::create([
            'round_id' => $round->id,
            'room_player_id' => $claimant->id,
            'artist_guess' => 'Test Artist',
            'title_guess' => 'Test Title',
        ]);

        session()->put('room_player_id', $voter->id);

        $preReveal = $this->getJson("/room/{$room->code}/state");
        $preReveal->assertSuccessful();

        expect($preReveal->json('round.amen.voting_open'))->toBeFalse();
        expect($preReveal->json('round.amen.guess'))->toBeNull();

        $room->update(['state' => RoomState::Reveal]);
        $round->update([
            'status' => RoundStatus::Revealed,
            'revealed_at' => now(),
        ]);

        $postReveal = $this->getJson("/room/{$room->code}/state");
        $postReveal->assertSuccessful();

        expect($postReveal->json('round.is_revealed'))->toBeTrue();
        expect($postReveal->json('round.amen.voting_open'))->toBeTrue();
        expect($postReveal->json('round.amen.can_vote'))->toBeTrue();
        expect($postReveal->json('round.amen.guess'))->toMatchArray([
            'artist' => 'Test Artist',
            'title' => 'Test Title',
        ]);
    });
});

describe('Round model AMEN helpers', function () {
    it('hasAmenClaim returns true when claimed', function () {
        $round = Round::factory()->active()->create();
        $player = RoomPlayer::factory()->create(['room_id' => $round->room_id]);

        expect($round->hasAmenClaim())->toBeFalse();

        $round->update(['amen_claimed_by_room_player_id' => $player->id]);

        expect($round->fresh()->hasAmenClaim())->toBeTrue();
    });

    it('isAmenResolved returns true when resolved', function () {
        $round = Round::factory()->active()->create();
        $player = RoomPlayer::factory()->create(['room_id' => $round->room_id]);

        $round->update([
            'amen_claimed_by_room_player_id' => $player->id,
            'amen_claimed_at' => now(),
        ]);

        expect($round->fresh()->isAmenResolved())->toBeFalse();

        $round->update(['amen_resolved_at' => now()]);

        expect($round->fresh()->isAmenResolved())->toBeTrue();
    });

    it('canClaimAmen returns false when not active', function () {
        $round = Round::factory()->revealed()->create();

        expect($round->canClaimAmen())->toBeFalse();
    });

    it('canClaimAmen returns false when already claimed', function () {
        $round = Round::factory()->active()->create();
        $player = RoomPlayer::factory()->create(['room_id' => $round->room_id]);

        $round->update(['amen_claimed_by_room_player_id' => $player->id]);

        expect($round->fresh()->canClaimAmen())->toBeFalse();
    });
});

describe('RoomPlayer AMEN helpers', function () {
    it('hasVotedForAmenInRound returns correct value', function () {
        $room = Room::factory()->inRound()->create();
        $round = Round::factory()->active()->create(['room_id' => $room->id]);
        $player = RoomPlayer::factory()->create(['room_id' => $room->id]);
        $claimant = RoomPlayer::factory()->create(['room_id' => $room->id]);

        $round->update([
            'amen_claimed_by_room_player_id' => $claimant->id,
            'amen_claimed_at' => now(),
        ]);

        expect($player->hasVotedForAmenInRound($round))->toBeFalse();

        AmenVote::create([
            'round_id' => $round->id,
            'voter_room_player_id' => $player->id,
            'vote' => true,
        ]);

        expect($player->fresh()->hasVotedForAmenInRound($round))->toBeTrue();
    });
});
