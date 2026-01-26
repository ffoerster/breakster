<?php

use App\Domain\Game\Enums\RoundStatus;
use App\Models\AmenGuess;
use App\Models\AmenVote;
use App\Models\Placement;
use App\Models\ReadyConfirmation;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\RoomUsedTrack;
use App\Models\Round;
use App\Models\TimelineItem;
use App\Models\Track;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

it('does not delete anything in dry-run mode', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));

    $finished = Room::factory()->finished()->create([
        'created_at' => now()->subHours(25),
        'updated_at' => now()->subHours(25),
    ]);

    $empty = Room::factory()->create([
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $stalled = Room::factory()->inRound()->create();
    $track = Track::factory()->create();
    $round = Round::factory()->create([
        'room_id' => $stalled->id,
        'track_id' => $track->id,
        'status' => RoundStatus::Active,
        'started_at' => now()->subHours(3),
    ]);
    $stalled->update(['current_round_id' => $round->id]);

    $exitCode = Artisan::call('game:prune-rooms', ['--dry-run' => true]);
    expect($exitCode)->toBe(0);

    expect(Room::query()->whereKey([$finished->id, $empty->id, $stalled->id])->count())->toBe(3);

    Carbon::setTestNow();
});

it('deletes finished rooms older than 24 hours', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));

    $oldFinished = Room::factory()->finished()->create([
        'created_at' => now()->subHours(25),
        'updated_at' => now()->subHours(25),
    ]);

    $recentFinished = Room::factory()->finished()->create([
        'created_at' => now()->subHours(23),
        'updated_at' => now()->subHours(23),
    ]);

    $exitCode = Artisan::call('game:prune-rooms');
    expect($exitCode)->toBe(0);

    expect(Room::query()->whereKey($oldFinished->id)->exists())->toBeFalse();
    expect(Room::query()->whereKey($recentFinished->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('deletes empty rooms older than 1 hour', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));

    $emptyOld = Room::factory()->create([
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $notEmptyOld = Room::factory()->create([
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);
    RoomPlayer::factory()->create(['room_id' => $notEmptyOld->id]);

    $exitCode = Artisan::call('game:prune-rooms');
    expect($exitCode)->toBe(0);

    expect(Room::query()->whereKey($emptyOld->id)->exists())->toBeFalse();
    expect(Room::query()->whereKey($notEmptyOld->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('deletes stalled in-progress rooms older than 2 hours', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));

    $stalled = Room::factory()->inRound()->create();
    $stalledRound = Round::factory()->create([
        'room_id' => $stalled->id,
        'track_id' => Track::factory()->create()->id,
        'status' => RoundStatus::Active,
        'started_at' => now()->subHours(3),
    ]);
    $stalled->update(['current_round_id' => $stalledRound->id]);

    $active = Room::factory()->inRound()->create();
    $activeRound = Round::factory()->create([
        'room_id' => $active->id,
        'track_id' => Track::factory()->create()->id,
        'status' => RoundStatus::Active,
        'started_at' => now()->subHours(1),
    ]);
    $active->update(['current_round_id' => $activeRound->id]);

    $exitCode = Artisan::call('game:prune-rooms');
    expect($exitCode)->toBe(0);

    expect(Room::query()->whereKey($stalled->id)->exists())->toBeFalse();
    expect(Room::query()->whereKey($active->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('cascades deletions for game-related data when pruning rooms', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));

    $room = Room::factory()->finished()->create([
        'created_at' => now()->subHours(25),
        'updated_at' => now()->subHours(25),
    ]);

    $track = Track::factory()->create();

    $player = RoomPlayer::factory()->create(['room_id' => $room->id]);
    $voter = RoomPlayer::factory()->create(['room_id' => $room->id]);

    $round = Round::factory()->create([
        'room_id' => $room->id,
        'track_id' => $track->id,
        'status' => RoundStatus::Revealed,
        'started_at' => now()->subHours(26),
        'revealed_at' => now()->subHours(26)->addMinutes(1),
    ]);

    $placement = Placement::factory()->create([
        'round_id' => $round->id,
        'room_player_id' => $player->id,
    ]);

    $timelineItem = TimelineItem::factory()->create([
        'room_player_id' => $player->id,
        'track_id' => $track->id,
        'position' => 0,
    ]);

    $usedTrack = RoomUsedTrack::create([
        'room_id' => $room->id,
        'track_id' => $track->id,
    ]);

    $amenGuess = AmenGuess::create([
        'round_id' => $round->id,
        'room_player_id' => $player->id,
        'artist_guess' => 'Test Artist',
        'title_guess' => 'Test Title',
    ]);

    $amenVote = AmenVote::create([
        'round_id' => $round->id,
        'voter_room_player_id' => $voter->id,
        'vote' => true,
    ]);

    $readyConfirmation = ReadyConfirmation::create([
        'round_id' => $round->id,
        'room_player_id' => $player->id,
        'confirmed_at' => now(),
    ]);

    $exitCode = Artisan::call('game:prune-rooms');
    expect($exitCode)->toBe(0);

    expect(Room::query()->whereKey($room->id)->exists())->toBeFalse();

    expect(RoomPlayer::query()->whereKey([$player->id, $voter->id])->count())->toBe(0);
    expect(Round::query()->whereKey($round->id)->exists())->toBeFalse();
    expect(Placement::query()->whereKey($placement->id)->exists())->toBeFalse();
    expect(TimelineItem::query()->whereKey($timelineItem->id)->exists())->toBeFalse();
    expect(RoomUsedTrack::query()->whereKey($usedTrack->id)->exists())->toBeFalse();
    expect(AmenGuess::query()->whereKey($amenGuess->id)->exists())->toBeFalse();
    expect(AmenVote::query()->whereKey($amenVote->id)->exists())->toBeFalse();
    expect(ReadyConfirmation::query()->whereKey($readyConfirmation->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});
