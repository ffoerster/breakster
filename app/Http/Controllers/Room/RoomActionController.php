<?php

declare(strict_types=1);

namespace App\Http\Controllers\Room;

use App\Domain\Game\GameService;
use App\Domain\Game\RoomSession;
use App\Domain\Providers\Spotify\SpotifyPlaybackService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetPlaybackProviderRequest;
use App\Http\Requests\SubmitAmenGuessRequest;
use App\Http\Requests\SubmitAmenVoteRequest;
use App\Http\Requests\SubmitPlacementRequest;
use App\Models\Provider;
use App\Models\Room;
use App\Models\Round;
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

    public function start(string $code, RoomSession $roomSession, GameService $gameService): JsonResponse
    {
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

        $placementEndsAt = $round->placement_ends_at;
        if ($placementEndsAt !== null && now()->gte($placementEndsAt)) {
            abort(409, 'Placement time has expired.');
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

    public function undoPlace(
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

        $placementEndsAt = $round->placement_ends_at;
        if ($placementEndsAt !== null && now()->gte($placementEndsAt)) {
            abort(409, 'Placement time has expired.');
        }

        try {
            $gameService->undoPlacement($player, $round);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function reveal(string $code, RoomSession $roomSession, GameService $gameService): JsonResponse
    {
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
        \Illuminate\Http\Request $request,
        string $code,
        RoomSession $roomSession,
        GameService $gameService,
        SpotifyPlaybackService $spotifyPlaybackService,
    ): JsonResponse {
        $room = $this->room($code);
        $roomSession->requireHost($room);

        $room->loadMissing('playbackProvider');

        if ($room->playbackProvider?->key === 'spotify' && $room->spotify_device_id) {
            $hasSpotifyToken = $request->user()
                ? $request->user()->providerTokens()->where('provider_key', 'spotify')->exists()
                : session()->has('spotify.guest_token');

            if ($hasSpotifyToken) {
                try {
                    $spotifyPlaybackService->pausePlayback($request->user(), $room->spotify_device_id);
                } catch (\RuntimeException) {
                    // Best effort - do not block the game flow.
                }
            }
        }

        try {
            $gameService->nextRound($room);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function finish(string $code, RoomSession $roomSession, GameService $gameService): JsonResponse
    {
        $room = $this->room($code);
        $roomSession->requireHost($room);

        $gameService->finishRoom($room);

        return response()->json(['ok' => true]);
    }

    public function markReady(string $code, RoomSession $roomSession, GameService $gameService): JsonResponse
    {
        $room = $this->room($code);
        $player = $roomSession->requirePlayer($room);

        $key = sprintf('ready-confirm:%d', $player->id);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Too many ready attempts. Please slow down.');
        }

        RateLimiter::hit($key, 60);

        try {
            $gameService->markReady($room, $player);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function setPlacementTimer(
        \Illuminate\Http\Request $request,
        string $code,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code);
        $roomSession->requireHost($room);

        $enabled = (bool) $request->input('enabled');

        try {
            $gameService->setPlacementTimerEnabled($room, $enabled);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function updateSettings(
        \Illuminate\Http\Request $request,
        string $code,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code);
        $roomSession->requireHost($room);

        $validated = $request->validate([
            'placement_timer_enabled' => ['sometimes', 'boolean'],
            'placement_timer_duration' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:300'],
            'placement_delay_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:30'],
            'max_rounds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'amen_enabled' => ['sometimes', 'boolean'],
        ]);

        try {
            $gameService->updateRoomSettings($room, $validated);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function claimAmen(
        string $code,
        int $roundId,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code);
        $player = $roomSession->requirePlayer($room);

        $round = $this->round($roundId, $room);

        $key = sprintf('amen-claim:%d', $player->id);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Too many AMEN attempts. Please slow down.');
        }

        RateLimiter::hit($key, 60);

        try {
            $gameService->claimAmen($round, $player);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function submitAmenGuess(
        SubmitAmenGuessRequest $request,
        string $code,
        int $roundId,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code);
        $player = $roomSession->requirePlayer($room);

        $round = $this->round($roundId, $room);

        $artist = $request->validated('artist');
        $title = $request->validated('title');

        try {
            $gameService->submitAmenGuess($round, $player, $artist, $title);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function submitAmenVote(
        SubmitAmenVoteRequest $request,
        string $code,
        int $roundId,
        RoomSession $roomSession,
        GameService $gameService,
    ): JsonResponse {
        $room = $this->room($code);
        $player = $roomSession->requirePlayer($room);

        $round = $this->round($roundId, $room);

        $vote = $request->boolean('vote');

        try {
            $gameService->submitAmenVote($round, $player, $vote);
        } catch (\RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    private function room(string $code): Room
    {
        return Room::query()->where('code', strtoupper($code))->firstOrFail();
    }

    private function round(int $roundId, Room $room): Round
    {
        return Round::query()
            ->where('id', $roundId)
            ->where('room_id', $room->id)
            ->firstOrFail();
    }
}
