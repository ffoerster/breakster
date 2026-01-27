<?php

declare(strict_types = 1);

namespace App\Http\Controllers\Room;

use App\Domain\Game\RoomStateData;
use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomShowController extends Controller
{
    public function __invoke(Request $request, string $code, RoomStateData $roomStateData): Response
    {
        $room = Room::query()
            ->with([
                'pack' => static fn($query) => $query->withCount(['spotifyTracks', 'tidalTracks', 'tracks']),
                'playbackProvider',
                'players',
                'currentRound.track',
            ])
            ->where('code', strtoupper($code))
            ->firstOrFail();

        return Inertia::render('Room/Show', $roomStateData->forRoom($room));
    }
}
