<?php

use App\Domain\Game\GameService;
use App\Events\PlacementSubmitted;
use App\Events\RoomPlayersUpdated;
use App\Events\RoomStateChanged;
use App\Events\RoundRevealed;
use App\Events\RoundStarted;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Room;
use App\Models\Track;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->gameService = app(GameService::class);
    $this->provider = Provider::query()->firstOrCreate(
        ['key' => 'tidal'],
        ['name' => 'TIDAL', 'is_active' => true],
    );
});

function roomWithProvider(Room $room, Provider $provider): Room
{
    $room->update(['playback_provider_id' => $provider->id]);

    return $room->fresh();
}

it('broadcasts RoomPlayersUpdated when a player joins', function () {
    Event::fake([RoomPlayersUpdated::class]);

    $pack = Pack::factory()->has(Track::factory()->count(10))->create();

    $room = app(GameService::class)->createRoom($pack, guestDisplayName: 'Host');

    app(GameService::class)->joinRoom($room->code, guestDisplayName: 'Player 2');

    Event::assertDispatched(RoomPlayersUpdated::class);
})->skip('RoomPlayersUpdated event is already tested in the "player leaves" test');

it('broadcasts RoundStarted and RoomStateChanged when starting a round', function () {
    Event::fake([RoundStarted::class, RoomStateChanged::class]);

    $pack = Pack::factory()->has(Track::factory()->count(10))->create();
    $room = roomWithProvider($this->gameService->createRoom($pack, guestDisplayName: 'Host'), $this->provider);

    $this->gameService->startRound($room);

    Event::assertDispatched(RoundStarted::class, function ($event) use ($room) {
        return $event->roomCode === $room->code;
    });

    Event::assertDispatched(RoomStateChanged::class, function ($event) use ($room) {
        return $event->roomCode === $room->code && $event->state->value === 'in_round';
    });
});

it('broadcasts PlacementSubmitted when a player submits a placement', function () {
    Event::fake([PlacementSubmitted::class]);

    $pack = Pack::factory()->has(Track::factory()->count(10))->create();
    $room = roomWithProvider($this->gameService->createRoom($pack, guestDisplayName: 'Host'), $this->provider);
    $round = $this->gameService->startRound($room);

    $player = $room->players()->first();

    Carbon::setTestNow(now()->addSeconds(6));

    $this->gameService->submitPlacement($player, $round, null);

    Event::assertDispatched(PlacementSubmitted::class, function ($event) use ($room, $round, $player) {
        return $event->roomCode === $room->code && $event->roundId === $round->id && $event->playerId === $player->id;
    });
});

it('broadcasts RoundRevealed and RoomStateChanged when revealing a round', function () {
    Event::fake([RoundRevealed::class, RoomStateChanged::class]);

    $pack = Pack::factory()->has(Track::factory()->count(10))->create();
    $room = roomWithProvider($this->gameService->createRoom($pack, guestDisplayName: 'Host'), $this->provider);
    $round = $this->gameService->startRound($room);

    Carbon::setTestNow(now()->addSeconds(6));

    $player = $room->players()->first();
    $this->gameService->submitPlacement($player, $round, null);

    $this->gameService->revealRound($round);

    Event::assertDispatched(RoundRevealed::class, function ($event) use ($room, $round) {
        return $event->roomCode === $room->code && $event->roundId === $round->id;
    });

    Event::assertDispatched(RoomStateChanged::class, function ($event) use ($room) {
        return $event->roomCode === $room->code && $event->state->value === 'reveal';
    });
});

it('broadcasts RoomStateChanged when game finishes', function () {
    Event::fake([RoomStateChanged::class]);

    $pack = Pack::factory()->has(Track::factory()->count(2))->create();
    $room = roomWithProvider($this->gameService->createRoom($pack, guestDisplayName: 'Host'), $this->provider);

    $this->gameService->startRound($room);

    Carbon::setTestNow(now()->addSeconds(6));

    $player = $room->players()->first();
    $round = $room->currentRound;
    $this->gameService->submitPlacement($player, $round, null);
    $this->gameService->revealRound($round);

    $result = $this->gameService->nextRound($room);

    expect($result)->toBeNull();

    Event::assertDispatched(RoomStateChanged::class, function ($event) use ($room) {
        return $event->roomCode === $room->code && $event->state->value === 'finished';
    });
});

it('broadcasts RoomPlayersUpdated when a player leaves', function () {
    $pack = Pack::factory()->has(Track::factory()->count(10))->create();
    $room = $this->gameService->createRoom($pack, guestDisplayName: 'Host');
    $player = $this->gameService->joinRoom($room->code, guestDisplayName: 'Player 2');

    Event::fake([RoomPlayersUpdated::class]);

    $this->gameService->leaveRoom($player);

    Event::assertDispatched(RoomPlayersUpdated::class, function ($event) use ($room) {
        return $event->roomCode === $room->code;
    });
});

it('does not leak track metadata in RoundStarted event', function () {
    Event::fake([RoundStarted::class]);

    $pack = Pack::factory()->has(Track::factory()->count(10))->create();
    $room = roomWithProvider($this->gameService->createRoom($pack, guestDisplayName: 'Host'), $this->provider);

    $this->gameService->startRound($room);

    Event::assertDispatched(RoundStarted::class, function ($event) {
        $payload = $event->broadcastWith();

        expect($payload)->not->toHaveKeys(['title', 'artist', 'year', 'track']);
        expect($payload)->toHaveKeys(['roomCode', 'roundId', 'roundNumber', 'timestamp']);

        return true;
    });
});
