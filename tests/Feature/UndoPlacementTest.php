<?php

use App\Domain\Game\Enums\RoundStatus;
use App\Domain\Game\GameService;
use App\Events\PlacementSubmitted;
use App\Models\Pack;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\Room;
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

function createUndoTestRoom($pack, $provider, $gameService, $guestDisplayName = 'Host'): Room
{
    $room = $gameService->createRoom($pack, guestDisplayName: $guestDisplayName);
    $room->update(['playback_provider_id' => $provider->id]);

    return $room->fresh();
}

describe('Undo Placement - GameService', function () {
    it('deletes an existing placement', function () {
        $room = createUndoTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room);

        // Place first
        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));
        $this->gameService->submitPlacement($player, $round, null);

        expect(Placement::where('round_id', $round->id)->where('room_player_id', $player->id)->exists())->toBeTrue();

        // Undo
        $this->gameService->undoPlacement($player, $round);

        expect(Placement::where('round_id', $round->id)->where('room_player_id', $player->id)->exists())->toBeFalse();

        Carbon::setTestNow();
    });

    it('broadcasts PlacementSubmitted event on undo', function () {
        Event::fake([PlacementSubmitted::class]);

        $room = createUndoTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room);

        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));
        $this->gameService->submitPlacement($player, $round, null);

        // Clear events from the submitPlacement call
        Event::fake([PlacementSubmitted::class]);

        $this->gameService->undoPlacement($player, $round);

        Event::assertDispatched(PlacementSubmitted::class, function ($event) use ($room, $round, $player) {
            return $event->roomCode === $room->code
                && $event->roundId === $round->id
                && $event->playerId === $player->id;
        });

        Carbon::setTestNow();
    });

    it('throws when no placement exists', function () {
        $room = createUndoTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room);

        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));

        expect(fn () => $this->gameService->undoPlacement($player, $round))
            ->toThrow(\RuntimeException::class, 'No placement to undo.');

        Carbon::setTestNow();
    });

    it('throws when round is not active', function () {
        $room = createUndoTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room);

        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));
        $this->gameService->submitPlacement($player, $round, null);

        // Mark round as revealed
        $round->update(['status' => RoundStatus::Revealed]);

        expect(fn () => $this->gameService->undoPlacement($player, $round->fresh()))
            ->toThrow(\RuntimeException::class, 'This round is not active.');

        Carbon::setTestNow();
    });

    it('throws when placement time has expired', function () {
        $room = createUndoTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room);

        // Place before timeout
        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));
        $this->gameService->submitPlacement($player, $round, null);

        // Travel past timeout
        Carbon::setTestNow($round->placement_ends_at->copy()->addSeconds(1));

        expect(fn () => $this->gameService->undoPlacement($player, $round))
            ->toThrow(\RuntimeException::class, 'Placement time has expired.');

        Carbon::setTestNow();
    });

    it('allows re-placement after undo', function () {
        $room = createUndoTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        Queue::fake();
        $round = $this->gameService->startRound($room);

        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));

        // Place, undo, place again
        $this->gameService->submitPlacement($player, $round, null);
        $this->gameService->undoPlacement($player, $round);

        $placement = $this->gameService->submitPlacement($player, $round, null);

        expect($placement)->toBeInstanceOf(Placement::class);
        expect(Placement::where('round_id', $round->id)->where('room_player_id', $player->id)->exists())->toBeTrue();

        Carbon::setTestNow();
    });
});

describe('Undo Placement - HTTP Endpoint', function () {
    it('returns ok when undoing a valid placement', function () {
        $room = createUndoTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        session()->put('room_player_id', $player->id);

        Queue::fake();
        $round = $this->gameService->startRound($room);

        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));
        $this->gameService->submitPlacement($player, $round, null);

        $response = $this->deleteJson("/room/{$room->code}/place");

        $response->assertSuccessful();
        $response->assertJson(['ok' => true]);

        expect(Placement::where('round_id', $round->id)->where('room_player_id', $player->id)->exists())->toBeFalse();

        Carbon::setTestNow();
    });

    it('returns 409 when no placement exists', function () {
        $room = createUndoTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        session()->put('room_player_id', $player->id);

        Queue::fake();
        $round = $this->gameService->startRound($room);

        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));

        $response = $this->deleteJson("/room/{$room->code}/place");

        $response->assertStatus(409);

        Carbon::setTestNow();
    });

    it('returns 409 when placement time has expired', function () {
        $room = createUndoTestRoom($this->pack, $this->provider, $this->gameService);
        $player = $room->players->first();

        session()->put('room_player_id', $player->id);

        Queue::fake();
        $round = $this->gameService->startRound($room);

        Carbon::setTestNow($round->placement_unlocks_at->copy()->addSeconds(1));
        $this->gameService->submitPlacement($player, $round, null);

        // Travel past timeout
        Carbon::setTestNow($round->placement_ends_at->copy()->addSeconds(1));

        $response = $this->deleteJson("/room/{$room->code}/place");

        $response->assertStatus(409);

        Carbon::setTestNow();
    });
});
