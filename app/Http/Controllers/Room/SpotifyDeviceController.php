<?php

declare(strict_types=1);

namespace App\Http\Controllers\Room;

use App\Domain\Game\Enums\RoomState;
use App\Domain\Providers\Spotify\SpotifyPlaybackService;
use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SpotifyDeviceController extends Controller
{
    public function __construct(
        private readonly SpotifyPlaybackService $playbackService,
    ) {}

    /**
     * Get available Spotify devices for the host.
     */
    public function index(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        $user = $request->user();

        // Check if requester is the host (either by user_id or session)
        $isHost = false;
        if ($user && $user->id === $room->host_user_id) {
            $isHost = true;
        } elseif (!$room->host_user_id) {
            // Guest host - check session via RoomPlayer
            $player = $room->players()->where('session_id', session()->getId())->first();
            $isHost = $player && $player->is_host;
        }

        if (!$isHost) {
            abort(403, 'Only the host can manage Spotify devices.');
        }

        try {
            $result = $this->playbackService->getDevices($user);

            return response()->json($result);
        } catch (RuntimeException $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                    'devices' => [],
                ],
                $e->getMessage() === 'Spotify Premium required for playback control.' ? 403 : 400,
            );
        }
    }

    /**
     * Set the Spotify device for the room.
     */
    public function store(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        $user = $request->user();

        // Check if requester is the host (either by user_id or session)
        $isHost = false;
        if ($user && $user->id === $room->host_user_id) {
            $isHost = true;
        } elseif (!$room->host_user_id) {
            // Guest host - check session via RoomPlayer
            $player = $room->players()->where('session_id', session()->getId())->first();
            $isHost = $player && $player->is_host;
        }

        if (!$isHost) {
            abort(403, 'Only the host can set Spotify devices.');
        }

        $validated = $request->validate([
            'device_id' => 'required|string',
        ]);

        $room->update([
            'spotify_device_id' => $validated['device_id'],
        ]);

        // Optionally transfer playback to make device active (without auto-play)
        try {
            $this->playbackService->transferPlayback($user, $validated['device_id'], play: false);
        } catch (RuntimeException) {
            // Ignore transfer errors - device might not be ready yet
        }

        return response()->json([
            'message' => 'Device set successfully',
            'device_id' => $validated['device_id'],
        ]);
    }

    /**
     * Trigger playback for the current round.
     */
    public function play(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', strtoupper($code))->firstOrFail();

        $user = $request->user();

        // Check if requester is the host (either by user_id or session)
        $isHost = false;
        if ($user && $user->id === $room->host_user_id) {
            $isHost = true;
        } elseif (!$room->host_user_id) {
            // Guest host - check session via RoomPlayer
            $player = $room->players()->where('session_id', session()->getId())->first();
            $isHost = $player && $player->is_host;
        }

        if (!$isHost) {
            abort(403, 'Only the host can control playback.');
        }

        if (!$room->spotify_device_id) {
            return response()->json([
                'error' => 'No Spotify device selected. Please select a device first.',
            ], 400);
        }

        if ($room->state !== RoomState::InRound) {
            return response()->json([
                'error' => 'No active round to play.',
            ], 400);
        }

        $round = $room->currentRound;
        if (!$round) {
            return response()->json([
                'error' => 'No track found for current round.',
            ], 400);
        }

        $round->load('track');
        if (!$round->track) {
            return response()->json([
                'error' => 'No track found for current round.',
            ], 400);
        }

        $spotifyTrackId = $round->track->provider_track_id;
        if (!$spotifyTrackId) {
            return response()->json([
                'error' => 'Track does not have a Spotify ID.',
            ], 400);
        }

        $spotifyUri = "spotify:track:{$spotifyTrackId}";

        try {
            $this->playbackService->startPlayback($user, $room->spotify_device_id, $spotifyUri);

            return response()->json([
                'message' => 'Playback started successfully',
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
