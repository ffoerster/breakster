<?php

declare(strict_types=1);

namespace App\Http\Controllers\Room;

use App\Domain\Game\GameService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRoomRequest;
use App\Models\Pack;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoomCreateController extends Controller
{
    public function create(): Response
    {
        $displayName = auth()->guard()->check() ? (string) auth()->guard()->user()?->name : '';

        $packs = Pack::query()
            ->where('is_active', true)
            ->whereHas('tracks', function ($query): void {
                $query->where('is_active', true);
            })
            ->withCount([
                'tracks as tracks_count' => function ($query): void {
                    $query->where('is_active', true);
                },
                'spotifyTracks as spotify_tracks_count' => function ($query): void {
                    $query->where('is_active', true);
                },
                'tidalTracks as tidal_tracks_count' => function ($query): void {
                    $query->where('is_active', true);
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Pack $pack): array => [
                'id' => $pack->id,
                'name' => $pack->name,
                'description' => $pack->description,
                'tracks_count' => $pack->tracks_count,
                'spotify_tracks_count' => $pack->spotify_tracks_count,
                'tidal_tracks_count' => $pack->tidal_tracks_count,
            ]);

        return Inertia::render('CreateRoom', [
            'initial_display_name' => $displayName,
            'packs' => $packs,
        ]);
    }

    public function store(CreateRoomRequest $request, GameService $gameService): RedirectResponse
    {
        $data = $request->validated();

        $pack = Pack::findOrFail($data['pack_id']);

        $room = $gameService->createRoom(
            pack: $pack,
            host: auth()->guard()->user(),
            guestDisplayName: $data['display_name'],
        );

        $player = $room->players()->where('is_host', true)->firstOrFail();
        session()->put('room_player_id', $player->id);

        return redirect()->route('room.show', ['code' => $room->code]);
    }
}
