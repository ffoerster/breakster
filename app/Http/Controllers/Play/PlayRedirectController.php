<?php

declare(strict_types = 1);

namespace App\Http\Controllers\Play;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;

class PlayRedirectController extends Controller
{
    public function __invoke(string $roomCode, int $roundId): RedirectResponse
    {
        $room = Room::query()
            ->with('playbackProvider')
            ->where('code', strtoupper($roomCode))
            ->firstOrFail();

        $provider = $room->playbackProvider;
        if (!$provider) {
            abort(404);
        }

        $round = Round::query()
            ->with('track')
            ->where('id', $roundId)
            ->where('room_id', $room->id)
            ->firstOrFail();

        $track = $round->track;
        if (!$track) {
            abort(404);
        }

        $destination = null;

        if ((int) $track->provider_id === (int) $room->playback_provider_id) {
            $destination = $track->playbackUrlForProvider($provider);
        }

        $destination ??= $track->externalUrlForProvider($provider);

        return redirect()->away($destination);
    }
}
