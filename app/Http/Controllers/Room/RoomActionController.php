<?php

declare(strict_types=1);

namespace App\Http\Controllers\Room;

use App\Domain\Game\GameService;
use App\Domain\Game\RoomSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetPlaybackProviderRequest;
use App\Http\Requests\SubmitPlacementRequest;
use App\Models\Provider;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class RoomActionController extends Controller
{
    public function setPlaybackProvider(
        SetPlaybackProviderRequest $request,
        string $code,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code);
        $roomSession->requireHost($room);

        $providerId = (int) $request->validated('playback_provider_id');

        $provider = Provider::query()
            ->whereIn('key', ['spotify', 'tidal'])
            ->where('is_active', true)
            ->findOrFail($providerId);

        try {
            $gameService->setPlaybackProvider($room, $provider);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function start(
        string $code,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code);
        $roomSession->requireHost($room);

        try {
            $gameService->startRound($room);
        } catch (\RuntimeException|\DomainException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function place(
        SubmitPlacementRequest $request,
        string $code,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code)->loadMissing('currentRound');
        $player = $roomSession->requirePlayer($room);

        $round = $room->currentRound;
        if (! $round) {
            abort(409, 'No active round.');
        }

        $key = sprintf('placement-submit:%d', $player->id);
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Too many placement attempts. Please slow down.');
        }

        RateLimiter::hit($key, 60);

        $placementUnlocksAt = $round->placement_unlocks_at;
        if ($placementUnlocksAt !== null && now()->lt($placementUnlocksAt)) {
            abort(409, 'Placement will unlock in a moment.');
        }

        $insertAfter = $request->validated('inserted_after_track_id');
        $insertAfterTrackId = $insertAfter === null ? null : (int) $insertAfter;

        try {
            $gameService->submitPlacement($player, $round, $insertAfterTrackId);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function reveal(
        string $code,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code)->loadMissing('currentRound');
        $roomSession->requireHost($room);

        if (! $room->currentRound) {
            abort(409, 'No active round.');
        }

        try {
            $gameService->revealRound($room->currentRound);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function next(
        string $code,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code);
        $roomSession->requireHost($room);

        try {
            $gameService->nextRound($room);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    private function room(string $code): Room
    {
        return Room::query()
            ->where('code', strtoupper($code))
            ->firstOrFail();
    }
}
