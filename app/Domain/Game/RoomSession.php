<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Models\Room;
use App\Models\RoomPlayer;

class RoomSession
{
    public function currentPlayer(Room $room): ?RoomPlayer
    {
        $playerId = session()->get('room_player_id');

        if ($playerId) {
            $player = RoomPlayer::find($playerId);

            if ($player && $player->room_id === $room->id) {
                return $player;
            }
        }

        if (auth()->guard()->check()) {
            return $room
                ->players()
                ->where('user_id', auth()->guard()->id())
                ->first();
        }

        return null;
    }

    public function requirePlayer(Room $room): RoomPlayer
    {
        $player = $this->currentPlayer($room);

        if (! $player) {
            abort(403, 'You are not in this room.');
        }

        return $player;
    }

    public function requireHost(Room $room): RoomPlayer
    {
        $player = $this->requirePlayer($room);

        if (! $player->is_host) {
            abort(403, 'Only the host can do that.');
        }

        return $player;
    }
}
