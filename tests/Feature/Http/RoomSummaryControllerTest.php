<?php

use App\Domain\Game\Enums\RoomState;
use App\Models\Pack;
use App\Models\Placement;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Round;
use App\Models\Track;

beforeEach(function () {
    $this->pack = Pack::factory()->create();
});

describe('RoomSummaryController', function () {
    it('returns 403 for non-members', function () {
        $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);
        RoomPlayer::factory()->for($room)->create();

        $this->get("/room/{$room->code}/summary")
            ->assertForbidden();
    });

    it('returns 409 for non-finished rooms', function () {
        $room = Room::factory()->for($this->pack)->create(['state' => RoomState::InRound]);
        $player = RoomPlayer::factory()->for($room)->create();

        session()->put('room_player_id', $player->id);

        $this->get("/room/{$room->code}/summary")
            ->assertStatus(409);
    });

    it('returns summary for finished room members', function () {
        $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);
        $player = RoomPlayer::factory()->for($room)->create(['score' => 100]);

        session()->put('room_player_id', $player->id);

        $track = Track::factory()->for($this->pack)->create();
        $round = Round::factory()->for($room)->create(['track_id' => $track->id]);
        Placement::factory()->for($round)->for($player, 'player')->correct()->create(['year_delta' => 0]);

        $response = $this->get("/room/{$room->code}/summary")
            ->assertOk()
            ->assertJsonStructure([
                'standings' => [
                    '*' => [
                        'rank',
                        'player_id',
                        'display_name',
                        'score',
                        'is_me',
                        'correct_placements',
                        'total_placements',
                        'accuracy_percentage',
                    ],
                ],
                'awards' => [
                    'most_accurate',
                    'biggest_miss',
                ],
                'amen_stats' => [
                    'total_claims',
                    'by_player',
                ],
                'game_stats' => [
                    'rounds_played',
                    'total_placements',
                    'overall_accuracy',
                ],
            ]);

        // Verify the current player is marked correctly
        $json = $response->json();
        $currentPlayerStanding = collect($json['standings'])->firstWhere('player_id', $player->id);
        expect($currentPlayerStanding['is_me'])->toBeTrue();
    });

    it('returns 404 for invalid room code', function () {
        $this->get('/room/INVALID/summary')
            ->assertNotFound();
    });

    it('returns summary with AMEN stats when claims exist', function () {
        $room = Room::factory()->for($this->pack)->create(['state' => RoomState::Finished]);
        $player = RoomPlayer::factory()->for($room)->create(['display_name' => 'Test Player']);

        session()->put('room_player_id', $player->id);

        $track = Track::factory()->for($this->pack)->create();
        Round::factory()->for($room)->create([
            'track_id' => $track->id,
            'amen_claimed_by_room_player_id' => $player->id,
            'amen_awarded_points' => 3,
        ]);

        $response = $this->get("/room/{$room->code}/summary")
            ->assertOk();

        $json = $response->json();
        expect($json['amen_stats']['total_claims'])->toBe(1)
            ->and($json['amen_stats']['by_player'])->toHaveCount(1)
            ->and($json['amen_stats']['by_player'][0]['display_name'])->toBe('Test Player')
            ->and($json['amen_stats']['by_player'][0]['confirmed'])->toBe(1)
            ->and($json['amen_stats']['by_player'][0]['net_points'])->toBe(3);
    });
});
