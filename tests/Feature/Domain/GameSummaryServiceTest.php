<?php

use App\Domain\Game\Enums\RoomState;
use App\Domain\Game\GameSummaryService;
use App\Models\Pack;
use App\Models\Placement;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Round;
use App\Models\Track;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new GameSummaryService;
    $this->pack = Pack::factory()->create();
});

describe('GameSummaryService', function () {
    describe('getFinalStandings', function () {
        it('returns players ordered by score', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);

            $player1 = RoomPlayer::factory()->for($room)->create(['score' => 100, 'display_name' => 'Alice']);
            $player2 = RoomPlayer::factory()->for($room)->create(['score' => 150, 'display_name' => 'Bob']);
            $player3 = RoomPlayer::factory()->for($room)->create(['score' => 50, 'display_name' => 'Charlie']);

            $standings = $this->service->getFinalStandings($room);

            expect($standings)->toHaveCount(3)
                ->and($standings[0]['display_name'])->toBe('Bob')
                ->and($standings[0]['rank'])->toBe(1)
                ->and($standings[1]['display_name'])->toBe('Alice')
                ->and($standings[1]['rank'])->toBe(2)
                ->and($standings[2]['display_name'])->toBe('Charlie')
                ->and($standings[2]['rank'])->toBe(3);
        });

        it('calculates accuracy percentage correctly', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);
            $player = RoomPlayer::factory()->for($room)->create(['score' => 100]);

            $track1 = Track::factory()->for($this->pack)->create();
            $track2 = Track::factory()->for($this->pack)->create();
            $round1 = Round::factory()->for($room)->create(['track_id' => $track1->id]);
            $round2 = Round::factory()->for($room)->create(['track_id' => $track2->id]);

            // 1 correct, 1 incorrect = 50% accuracy
            Placement::factory()->for($round1)->for($player, 'player')->correct()->create();
            Placement::factory()->for($round2)->for($player, 'player')->incorrect()->create();

            $standings = $this->service->getFinalStandings($room);

            expect($standings[0]['accuracy_percentage'])->toBe(50)
                ->and($standings[0]['correct_placements'])->toBe(1)
                ->and($standings[0]['total_placements'])->toBe(2);
        });

        it('marks current player with is_me', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);

            $player1 = RoomPlayer::factory()->for($room)->create(['score' => 100]);
            $player2 = RoomPlayer::factory()->for($room)->create(['score' => 50]);

            $standings = $this->service->getFinalStandings($room, $player2);

            expect($standings[0]['is_me'])->toBeFalse()
                ->and($standings[1]['is_me'])->toBeTrue();
        });
    });

    describe('getMostAccuratePlayer', function () {
        it('returns player with lowest average year delta', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);

            $player1 = RoomPlayer::factory()->for($room)->create(['display_name' => 'Alice']);
            $player2 = RoomPlayer::factory()->for($room)->create(['display_name' => 'Bob']);

            $track = Track::factory()->for($this->pack)->create();
            $round = Round::factory()->for($room)->create(['track_id' => $track->id]);

            // Alice: avg delta = 5
            Placement::factory()->for($round)->for($player1, 'player')->create(['year_delta' => 5, 'is_correct' => false]);

            // Bob: avg delta = 2
            Placement::factory()->for($round)->for($player2, 'player')->create(['year_delta' => 2, 'is_correct' => false]);

            $mostAccurate = $this->service->getMostAccuratePlayer($room);

            expect($mostAccurate['display_name'])->toBe('Bob')
                ->and($mostAccurate['average_year_delta'])->toBe(2.0);
        });

        it('returns null when no rounds played', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);
            RoomPlayer::factory()->for($room)->create();

            $mostAccurate = $this->service->getMostAccuratePlayer($room);

            expect($mostAccurate)->toBeNull();
        });
    });

    describe('getBiggestMiss', function () {
        it('returns placement with highest year delta', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);

            $player1 = RoomPlayer::factory()->for($room)->create(['display_name' => 'Alice']);
            $player2 = RoomPlayer::factory()->for($room)->create(['display_name' => 'Bob']);

            $track = Track::factory()->for($this->pack)->create([
                'title' => 'Big Miss Track',
                'artist' => 'Test Artist',
                'release_date' => Carbon::parse('1990-01-01'),
            ]);
            $round = Round::factory()->for($room)->create([
                'track_id' => $track->id,
                'round_number' => 3,
            ]);

            // Alice: delta = 5
            Placement::factory()->for($round)->for($player1, 'player')->create(['year_delta' => 5, 'is_correct' => false]);

            // Bob: delta = 25 (biggest miss)
            Placement::factory()->for($round)->for($player2, 'player')->create(['year_delta' => 25, 'is_correct' => false]);

            $biggestMiss = $this->service->getBiggestMiss($room);

            expect($biggestMiss['display_name'])->toBe('Bob')
                ->and($biggestMiss['year_delta'])->toBe(25)
                ->and($biggestMiss['round_number'])->toBe(3)
                ->and($biggestMiss['track']['title'])->toBe('Big Miss Track');
        });

        it('returns null when all placements are correct', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);
            $player = RoomPlayer::factory()->for($room)->create();

            $track = Track::factory()->for($this->pack)->create();
            $round = Round::factory()->for($room)->create(['track_id' => $track->id]);

            // All correct placements have year_delta = 0
            Placement::factory()->for($round)->for($player, 'player')->correct()->create(['year_delta' => 0]);

            $biggestMiss = $this->service->getBiggestMiss($room);

            expect($biggestMiss)->toBeNull();
        });
    });

    describe('getAmenStats', function () {
        it('aggregates AMEN claims by player', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);

            $player1 = RoomPlayer::factory()->for($room)->create(['display_name' => 'Alice']);
            $player2 = RoomPlayer::factory()->for($room)->create(['display_name' => 'Bob']);

            $track1 = Track::factory()->for($this->pack)->create();
            $track2 = Track::factory()->for($this->pack)->create();
            $track3 = Track::factory()->for($this->pack)->create();

            // Alice: 2 claims, 1 confirmed (+3), 1 rejected (-2)
            Round::factory()->for($room)->create([
                'track_id' => $track1->id,
                'amen_claimed_by_room_player_id' => $player1->id,
                'amen_awarded_points' => 3,
            ]);
            Round::factory()->for($room)->create([
                'track_id' => $track2->id,
                'amen_claimed_by_room_player_id' => $player1->id,
                'amen_awarded_points' => -2,
            ]);

            // Bob: 1 claim, 1 tie (0 points)
            Round::factory()->for($room)->create([
                'track_id' => $track3->id,
                'amen_claimed_by_room_player_id' => $player2->id,
                'amen_awarded_points' => 0,
            ]);

            $stats = $this->service->getAmenStats($room);

            expect($stats['total_claims'])->toBe(3);

            // Find Alice's stats (should be first due to higher net points)
            $aliceStats = collect($stats['by_player'])->firstWhere('display_name', 'Alice');
            expect($aliceStats['claims'])->toBe(2)
                ->and($aliceStats['confirmed'])->toBe(1)
                ->and($aliceStats['rejected'])->toBe(1)
                ->and($aliceStats['ties'])->toBe(0)
                ->and($aliceStats['net_points'])->toBe(1);

            $bobStats = collect($stats['by_player'])->firstWhere('display_name', 'Bob');
            expect($bobStats['claims'])->toBe(1)
                ->and($bobStats['ties'])->toBe(1)
                ->and($bobStats['net_points'])->toBe(0);
        });

        it('returns empty stats when no AMEN claims', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);
            RoomPlayer::factory()->for($room)->create();

            $track = Track::factory()->for($this->pack)->create();
            Round::factory()->for($room)->create(['track_id' => $track->id]);

            $stats = $this->service->getAmenStats($room);

            expect($stats['total_claims'])->toBe(0)
                ->and($stats['by_player'])->toBeEmpty();
        });
    });

    describe('getGameStats', function () {
        it('calculates overall game statistics', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);

            $player1 = RoomPlayer::factory()->for($room)->create();
            $player2 = RoomPlayer::factory()->for($room)->create();

            $track1 = Track::factory()->for($this->pack)->create();
            $track2 = Track::factory()->for($this->pack)->create();
            $track3 = Track::factory()->for($this->pack)->create();

            // 3 rounds
            $round1 = Round::factory()->for($room)->create(['track_id' => $track1->id]);
            $round2 = Round::factory()->for($room)->create(['track_id' => $track2->id]);
            $round3 = Round::factory()->for($room)->create(['track_id' => $track3->id]);

            // 6 placements total, 4 correct = 67% accuracy
            Placement::factory()->for($round1)->for($player1, 'player')->correct()->create();
            Placement::factory()->for($round1)->for($player2, 'player')->correct()->create();
            Placement::factory()->for($round2)->for($player1, 'player')->correct()->create();
            Placement::factory()->for($round2)->for($player2, 'player')->incorrect()->create();
            Placement::factory()->for($round3)->for($player1, 'player')->correct()->create();
            Placement::factory()->for($round3)->for($player2, 'player')->incorrect()->create();

            $stats = $this->service->getGameStats($room);

            expect($stats['rounds_played'])->toBe(3)
                ->and($stats['total_placements'])->toBe(6)
                ->and($stats['overall_accuracy'])->toBe(67);
        });

        it('handles empty game', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);

            $stats = $this->service->getGameStats($room);

            expect($stats['rounds_played'])->toBe(0)
                ->and($stats['total_placements'])->toBe(0)
                ->and($stats['overall_accuracy'])->toBe(0);
        });
    });

    describe('getSummary', function () {
        it('returns complete summary structure', function () {
            $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);
            $player = RoomPlayer::factory()->for($room)->create(['score' => 100]);

            $track = Track::factory()->for($this->pack)->create();
            $round = Round::factory()->for($room)->create(['track_id' => $track->id]);
            Placement::factory()->for($round)->for($player, 'player')->correct()->create(['year_delta' => 0]);

            $summary = $this->service->getSummary($room, $player);

            expect($summary)->toHaveKeys(['standings', 'awards', 'amen_stats', 'game_stats'])
                ->and($summary['awards'])->toHaveKeys(['most_accurate', 'biggest_miss'])
                ->and($summary['amen_stats'])->toHaveKeys(['total_claims', 'by_player'])
                ->and($summary['game_stats'])->toHaveKeys(['rounds_played', 'total_placements', 'overall_accuracy']);
        });
    });
});
