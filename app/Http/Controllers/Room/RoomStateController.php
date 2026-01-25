<?php

declare(strict_types=1);

namespace App\Http\Controllers\Room;

use App\Domain\Game\RoomSession;
use App\Domain\Game\RoomStateData;
use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomStateController extends Controller
{
    public function __invoke(Request $request, string $code, RoomStateData $roomStateData, RoomSession $roomSession): JsonResponse
    {
        $room = Room::query()
            ->with([
                'pack' => fn ($query) => $query->withCount(['spotifyTracks', 'tidalTracks', 'tracks']),
                'playbackProvider',
                'players',
                'currentRound.track',
            ])
            ->where('code', strtoupper($code))
            ->firstOrFail();

        $roomSession->requirePlayer($room);

        return response()->json($roomStateData->forRoom($room));
    }
}
