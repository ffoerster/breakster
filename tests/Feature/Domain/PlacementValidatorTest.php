<?php

use App\Domain\Game\PlacementValidator;
use App\Models\Pack;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\TimelineItem;
use App\Models\Track;
use Carbon\Carbon;

beforeEach(function () {
    $this->validator = new PlacementValidator;
    $this->pack = Pack::factory()->create();
});

describe('PlacementValidator', function () {
    it('returns true for empty timeline', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();
        $track = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-06-15'),
        ]);

        $isCorrect = $this->validator->isCorrect($player, $track, null);

        expect($isCorrect)->toBeTrue();
    });

    it('validates correct placement at beginning', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track in timeline: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from 1985 should be correct at beginning
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1985-01-01'),
        ]);

        $isCorrect = $this->validator->isCorrect($player, $newTrack, null);

        expect($isCorrect)->toBeTrue();
    });

    it('validates incorrect placement at beginning', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track in timeline: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from 1995 should be incorrect at beginning
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1995-01-01'),
        ]);

        $isCorrect = $this->validator->isCorrect($player, $newTrack, null);

        expect($isCorrect)->toBeFalse();
    });

    it('validates correct placement in middle', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Timeline: 1980 -> 2000
        $track1980 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1980-01-01'),
        ]);
        $track2000 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        TimelineItem::factory()->for($player)->create([
            'track_id' => $track1980->id,
            'position' => 0,
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $track2000->id,
            'position' => 1,
        ]);

        // New track from 1990 placed after 1980 should be correct
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);

        $isCorrect = $this->validator->isCorrect($player, $newTrack, $track1980->id);

        expect($isCorrect)->toBeTrue();
    });

    it('validates incorrect placement in middle', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Timeline: 1980 -> 2000
        $track1980 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1980-01-01'),
        ]);
        $track2000 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        TimelineItem::factory()->for($player)->create([
            'track_id' => $track1980->id,
            'position' => 0,
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $track2000->id,
            'position' => 1,
        ]);

        // New track from 2010 placed after 1980 should be incorrect (too late)
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2010-01-01'),
        ]);

        $isCorrect = $this->validator->isCorrect($player, $newTrack, $track1980->id);

        expect($isCorrect)->toBeFalse();
    });

    it('validates correct placement at end', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track in timeline: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from 2000 placed after 1990 should be correct
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        $isCorrect = $this->validator->isCorrect($player, $newTrack, $existingTrack->id);

        expect($isCorrect)->toBeTrue();
    });

    it('validates incorrect placement at end', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track in timeline: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from 1980 placed after 1990 should be incorrect
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1980-01-01'),
        ]);

        $isCorrect = $this->validator->isCorrect($player, $newTrack, $existingTrack->id);

        expect($isCorrect)->toBeFalse();
    });

    it('allows equal dates at beginning', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track in timeline: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from same date placed at beginning should be correct
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);

        $isCorrect = $this->validator->isCorrect($player, $newTrack, null);

        expect($isCorrect)->toBeTrue();
    });

    it('allows equal dates in middle', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Timeline: 1980 -> 2000
        $track1980 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1980-01-01'),
        ]);
        $track2000 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        TimelineItem::factory()->for($player)->create([
            'track_id' => $track1980->id,
            'position' => 0,
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $track2000->id,
            'position' => 1,
        ]);

        // New track with same date as first, placed after first should be correct
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1980-01-01'),
        ]);

        $isCorrect = $this->validator->isCorrect($player, $newTrack, $track1980->id);

        expect($isCorrect)->toBeTrue();
    });

    it('finds correct position for empty timeline', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();
        $track = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        $position = $this->validator->findCorrectPosition($player, $track);

        expect($position)->toBe(0);
    });

    it('finds correct position at beginning', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);

        $position = $this->validator->findCorrectPosition($player, $newTrack);

        expect($position)->toBe(0);
    });

    it('finds correct position at end', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        $position = $this->validator->findCorrectPosition($player, $newTrack);

        expect($position)->toBe(1);
    });
});

describe('calculateYearDelta', function () {
    it('returns zero for empty timeline', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();
        $track = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        $delta = $this->validator->calculateYearDelta($player, $track, null);

        expect($delta)->toBe(0);
    });

    it('returns zero for correct placement at beginning', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from 1985 at beginning is correct
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1985-01-01'),
        ]);

        $delta = $this->validator->calculateYearDelta($player, $newTrack, null);

        expect($delta)->toBe(0);
    });

    it('calculates delta when placed too late at beginning', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from 1995 placed at beginning - 5 years too late
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1995-01-01'),
        ]);

        $delta = $this->validator->calculateYearDelta($player, $newTrack, null);

        expect($delta)->toBe(5);
    });

    it('returns zero for correct placement in middle', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Timeline: 1980 -> 2000
        $track1980 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1980-01-01'),
        ]);
        $track2000 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        TimelineItem::factory()->for($player)->create([
            'track_id' => $track1980->id,
            'position' => 0,
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $track2000->id,
            'position' => 1,
        ]);

        // New track from 1990 placed after 1980 is correct
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);

        $delta = $this->validator->calculateYearDelta($player, $newTrack, $track1980->id);

        expect($delta)->toBe(0);
    });

    it('calculates delta when placed too early in middle', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Timeline: 1980 -> 2000
        $track1980 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1980-01-01'),
        ]);
        $track2000 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        TimelineItem::factory()->for($player)->create([
            'track_id' => $track1980->id,
            'position' => 0,
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $track2000->id,
            'position' => 1,
        ]);

        // New track from 1970 placed after 1980 - 10 years too early
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1970-01-01'),
        ]);

        $delta = $this->validator->calculateYearDelta($player, $newTrack, $track1980->id);

        expect($delta)->toBe(10);
    });

    it('calculates delta when placed too late in middle', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Timeline: 1980 -> 2000
        $track1980 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1980-01-01'),
        ]);
        $track2000 = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        TimelineItem::factory()->for($player)->create([
            'track_id' => $track1980->id,
            'position' => 0,
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $track2000->id,
            'position' => 1,
        ]);

        // New track from 2010 placed after 1980 - 10 years too late
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2010-01-01'),
        ]);

        $delta = $this->validator->calculateYearDelta($player, $newTrack, $track1980->id);

        expect($delta)->toBe(10);
    });

    it('returns zero for correct placement at end', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from 2000 placed after 1990 is correct
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('2000-01-01'),
        ]);

        $delta = $this->validator->calculateYearDelta($player, $newTrack, $existingTrack->id);

        expect($delta)->toBe(0);
    });

    it('calculates delta when placed too early at end', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-01-01'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track from 1980 placed after 1990 - 10 years too early
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1980-01-01'),
        ]);

        $delta = $this->validator->calculateYearDelta($player, $newTrack, $existingTrack->id);

        expect($delta)->toBe(10);
    });

    it('returns zero for same year tracks', function () {
        $room = Room::factory()->for($this->pack)->create();
        $player = RoomPlayer::factory()->for($room)->create();

        // Existing track: 1990
        $existingTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-06-15'),
        ]);
        TimelineItem::factory()->for($player)->create([
            'track_id' => $existingTrack->id,
            'position' => 0,
        ]);

        // New track also from 1990 (different month)
        $newTrack = Track::factory()->for($this->pack)->create([
            'release_date' => Carbon::parse('1990-03-01'),
        ]);

        // Placed at beginning should be correct (same year)
        $delta = $this->validator->calculateYearDelta($player, $newTrack, null);

        expect($delta)->toBe(0);
    });
});
