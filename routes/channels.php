<?php

use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 |--------------------------------------------------------------------------
 | Room Broadcast Channels
 |--------------------------------------------------------------------------
 |
 | Room channels allow real-time updates for game state changes.
 | Access is limited to joined players (session-based guests or authenticated users).
 |
 */

Broadcast::channel('room.{roomCode}', function (?User $user, string $roomCode): bool {
    $room = Room::query()->where('code', strtoupper($roomCode))->first();

    if (! $room) {
        return false;
    }

    $playerId = session()->get('room_player_id');

    if (is_string($playerId) || is_int($playerId)) {
        $player = RoomPlayer::find((int) $playerId);

        if ($player && $player->room_id === $room->id) {
            return true;
        }
    }

    if ($user) {
        return $room->players()->where('user_id', $user->id)->exists();
    }

    return false;
});
