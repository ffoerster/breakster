<?php

declare(strict_types=1);

namespace App\Http\Controllers\Room;

use App\Domain\Game\GameService;
use App\Http\Controllers\Controller;
use App\Http\Requests\JoinRoomRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class RoomJoinController extends Controller
{
    public function create(Request $request): Response
    {
        $displayName = auth()->guard()->check() ? (string) auth()->guard()->user()?->name : '';

        $code = $request->string('code')->trim()->upper()->toString();

        return Inertia::render('JoinRoom', [
            'initial_display_name' => $displayName,
            'initial_code' => $code,
        ]);
    }

    public function store(JoinRoomRequest $request, GameService $gameService): RedirectResponse
    {
        $key = sprintf('room-join:%s', $request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors([
                'code' => 'Too many join attempts. Please wait a moment and try again.',
            ]);
        }

        RateLimiter::hit($key, 60);

        $data = $request->validated();

        try {
            $player = $gameService->joinRoom(
                code: strtoupper($data['code']),
                user: auth()->guard()->user(),
                guestDisplayName: $data['display_name'],
            );

            session()->put('room_player_id', $player->id);

            return redirect()->route('room.show', ['code' => $player->room->code]);
        } catch (\RuntimeException $e) {
            return back()->withErrors([
                'code' => $e->getMessage(),
            ])->withInput();
        }
    }
}
