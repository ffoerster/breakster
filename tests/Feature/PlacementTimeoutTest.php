<?php

use App\Domain\Game\Enums\RoundStatus;
use App\Domain\Game\GameService;
use App\Events\PlacementTimeoutResolved;
use App\Jobs\ResolvePlacementTimeoutJob;
use App\Models\Pack;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\Room;
use App\Models\Round;
use App\Models\Track;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->gameService = app(GameService::class);
    $this->provider = Provider::query()->firstOrCreate(['key' => 'tidal'], ['name' => 'TIDAL', 'is_active' => true]);
    $this->pack = Pack::factory()->create();
    Track::factory()
        ->count(20)
        ->for($this->pack)
        ->state(['provider_id' => $this->provider->id])
        ->create();
});

/**
 * Helper to create a room with the playback provider set.
 */
function createTimeoutTestRoom($pack, $provider, $gameService, $guestDisplayName = 'Host'): Room
{
    $room = $gameService->createRoom($pack, guestDisplayName: $guestDisplayName);
    $room->update(['playback_provider_id' => $provider->id]);

    return $room->fresh();
}

describe('Placement Timeout - Round Setup', function () {
    it('sets placement_ends_at when starting a round', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $this->gameService->joinRoom($room->code, guestDisplayName: 'Player');

        Queue::fake();

        $round = $this->gameService->startRound($room);

        expect($round->placement_unlocks_at)->not->toBeNull();
        expect($round->placement_ends_at)->not->toBeNull();
        expect($round->placement_ends_at->gt($round->placement_unlocks_at))->toBeTrue();

        // Default: 3s delay + 12s duration = 15s total from start
        $expectedEndsAt = $round->started_at->copy()->addSeconds(15);
        expect($round->placement_ends_at->diffInSeconds($expectedEndsAt))->toBeLessThan(2);
    });

    it('dispatches timeout job with correct delay', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $this->gameService->joinRoom($room->code, guestDisplayName: 'Player');

        Queue::fake();

        $round = $this->gameService->startRound($room);

        Queue::assertPushed(ResolvePlacementTimeoutJob::class, function ($job) use ($round) {
            return $job->roundId === $round->id;
        });
    });
});

describe('Placement Timeout - Submission Validation', function () {
    it('rejects placements after timeout', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $this->gameService->joinRoom($room->code, guestDisplayName: 'Player');

        Queue::fake();
        $round = $this->gameService->startRound($room);

        // Travel to after placement_ends_at
        Carbon::setTestNow($round->placement_ends_at->copy()->addSeconds(1));

        expect(fn () => $this->gameService->submitPlacement($player, $round, null))
            ->toThrow(\RuntimeException::class, 'Placement time has expired.');

        Carbon::setTestNow();
    });

    it('accepts placements before timeout', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $this->gameService->joinRoom($room->code, guestDisplayName: 'Player');

        Queue::fake();
        $round = $this->gameService->startRound($room);

        // Travel to after unlock but before timeout
        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(5));

        $placement = $this->gameService->submitPlacement($player, $round, null);

        expect($placement)->toBeInstanceOf(Placement::class);
        expect($placement->is_auto_placed)->toBeFalse();

        Carbon::setTestNow();
    });

    it('allows auto-placement after timeout', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $this->gameService->joinRoom($room->code, guestDisplayName: 'Player');

        Queue::fake();
        $round = $this->gameService->startRound($room);

        // Travel to after placement_ends_at
        Carbon::setTestNow($round->placement_ends_at->copy()->addSeconds(1));

        // Auto-placement should still work
        $placement = $this->gameService->submitPlacement($player, $round, null, isAutoPlaced: true);

        expect($placement)->toBeInstanceOf(Placement::class);
        expect($placement->is_auto_placed)->toBeTrue();

        Carbon::setTestNow();
    });
});

describe('Placement Timeout - Auto-Placement', function () {
    it('auto-places players who have not submitted', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room);

        // No one has placed
        expect($round->placements()->count())->toBe(0);

        Carbon::setTestNow($round->placement_ends_at->copy()->addSeconds(1));

        // Auto-place the player
        $count = $this->gameService->autoPlaceMissingPlayers($round);

        expect($count)->toBeGreaterThanOrEqual(1);

        // Player should now have a placement
        $placement = $round->placements()->where('room_player_id', $player->id)->first();
        expect($placement)->not->toBeNull();
        expect($placement->is_auto_placed)->toBeTrue();

        Carbon::setTestNow();
    });

    it('marks auto-placed placements correctly', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room);

        Carbon::setTestNow($round->placement_ends_at->copy()->addSeconds(1));

        $this->gameService->autoPlaceMissingPlayers($round);

        $placement = $round->placements()->where('room_player_id', $player->id)->first();
        expect($placement->is_auto_placed)->toBeTrue();

        Carbon::setTestNow();
    });

    it('does not re-place players who already placed', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room);

        // Player places manually before timeout
        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));
        $originalPlacement = $this->gameService->submitPlacement($player, $round, null);
        expect($originalPlacement->is_auto_placed)->toBeFalse();

        Carbon::setTestNow($round->placement_ends_at->copy()->addSeconds(1));

        // Auto-place - should not affect the existing placement
        $this->gameService->autoPlaceMissingPlayers($round);

        // Original placement should be unchanged
        $refreshedPlacement = Placement::find($originalPlacement->id);
        expect($refreshedPlacement->is_auto_placed)->toBeFalse();

        Carbon::setTestNow();
    });

    it('returns 0 for inactive round', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $this->gameService->joinRoom($room->code, guestDisplayName: 'Player');

        Queue::fake();
        $round = $this->gameService->startRound($room);
        $round->status = RoundStatus::Revealed;
        $round->save();

        $count = $this->gameService->autoPlaceMissingPlayers($round);

        expect($count)->toBe(0);
    });
});

describe('Placement Timeout - Job', function () {
    it('job auto-places and broadcasts event', function () {
        Event::fake([PlacementTimeoutResolved::class]);

        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);

        Queue::fake();
        $round = $this->gameService->startRound($room);

        Carbon::setTestNow($round->placement_ends_at->copy()->addSeconds(1));

        // Run the job directly - it will load the round fresh from DB
        $job = new ResolvePlacementTimeoutJob($round->id);
        $job->handle($this->gameService);

        Event::assertDispatched(PlacementTimeoutResolved::class, function ($event) use ($room, $round) {
            return $event->roomCode === $room->code
                && $event->roundId === $round->id
                && $event->autoPlacedCount >= 1;
        });

        Carbon::setTestNow();
    });

    it('job does nothing for inactive round', function () {
        Event::fake([PlacementTimeoutResolved::class]);

        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $this->gameService->joinRoom($room->code, guestDisplayName: 'Player');

        Queue::fake();
        $round = $this->gameService->startRound($room->fresh());

        // Mark round as revealed
        $round->status = RoundStatus::Revealed;
        $round->save();

        Carbon::setTestNow($round->placement_ends_at->copy()->addSeconds(1));

        $job = new ResolvePlacementTimeoutJob($round->id);
        $job->handle($this->gameService);

        Event::assertNotDispatched(PlacementTimeoutResolved::class);

        Carbon::setTestNow();
    });

    it('job does nothing if placement period not expired', function () {
        Event::fake([PlacementTimeoutResolved::class]);

        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $this->gameService->joinRoom($room->code, guestDisplayName: 'Player');

        Queue::fake();
        $round = $this->gameService->startRound($room->fresh());

        // Time is before placement_ends_at
        Carbon::setTestNow($round->placement_ends_at->copy()->subSeconds(5));

        $job = new ResolvePlacementTimeoutJob($round->id);
        $job->handle($this->gameService);

        Event::assertNotDispatched(PlacementTimeoutResolved::class);

        Carbon::setTestNow();
    });

    it('job handles missing round gracefully', function () {
        Event::fake([PlacementTimeoutResolved::class]);

        $job = new ResolvePlacementTimeoutJob(999999);
        $job->handle($this->gameService);

        Event::assertNotDispatched(PlacementTimeoutResolved::class);
    });
});

describe('Placement Timeout - Round Model', function () {
    it('isPlacementPeriodExpired returns true after timeout', function () {
        $round = Round::factory()->create([
            'placement_ends_at' => now()->subMinute(),
        ]);

        expect($round->isPlacementPeriodExpired())->toBeTrue();
    });

    it('isPlacementPeriodExpired returns false before timeout', function () {
        $round = Round::factory()->create([
            'placement_ends_at' => now()->addMinute(),
        ]);

        expect($round->isPlacementPeriodExpired())->toBeFalse();
    });

    it('isPlacementPeriodExpired returns false when null', function () {
        $round = Round::factory()->create([
            'placement_ends_at' => null,
        ]);

        expect($round->isPlacementPeriodExpired())->toBeFalse();
    });
});

describe('Placement Timer Toggle', function () {
    it('does not set placement_ends_at when timer is disabled', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $room->update(['placement_timer_enabled' => false]);
        $room = $room->fresh();

        Queue::fake();

        $round = $this->gameService->startRound($room);

        expect($round->placement_unlocks_at)->not->toBeNull();
        expect($round->placement_ends_at)->toBeNull();

        // Should not dispatch timeout job
        Queue::assertNotPushed(ResolvePlacementTimeoutJob::class);
    });

    it('sets placement_ends_at when timer is enabled', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $room->update(['placement_timer_enabled' => true]);
        $room = $room->fresh();

        Queue::fake();

        $round = $this->gameService->startRound($room);

        expect($round->placement_unlocks_at)->not->toBeNull();
        expect($round->placement_ends_at)->not->toBeNull();

        Queue::assertPushed(ResolvePlacementTimeoutJob::class);
    });

    it('allows toggling the timer setting', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);

        expect($room->placement_timer_enabled)->toBeTrue();

        $this->gameService->setPlacementTimerEnabled($room, false);
        $room->refresh();

        expect($room->placement_timer_enabled)->toBeFalse();

        $this->gameService->setPlacementTimerEnabled($room, true);
        $room->refresh();

        expect($room->placement_timer_enabled)->toBeTrue();
    });

    it('only allows toggling in lobby', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);

        Queue::fake();
        $this->gameService->startRound($room);

        expect(fn () => $this->gameService->setPlacementTimerEnabled($room->fresh(), false))
            ->toThrow(\RuntimeException::class, 'Placement timer can only be changed in the lobby.');
    });

    it('accepts placements without time limit when timer disabled', function () {
        $room = createTimeoutTestRoom($this->pack, $this->provider, $this->gameService);
        $room->update(['placement_timer_enabled' => false]);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room->fresh());

        // No placement_ends_at means no timeout
        expect($round->placement_ends_at)->toBeNull();

        // Travel way into the future - should still accept placements
        Carbon::setTestNow(now()->addHours(1));

        $placement = $this->gameService->submitPlacement($player, $round, null);

        expect($placement)->toBeInstanceOf(Placement::class);
        expect($placement->is_auto_placed)->toBeFalse();

        Carbon::setTestNow();
    });
});
