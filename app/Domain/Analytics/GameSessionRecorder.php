<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Domain\Game\Enums\RoomState;
use App\Models\GameSession;
use App\Models\Room;

class GameSessionRecorder
{
    public function record(Room $room): void
    {
        if ($room->state !== RoomState::Finished) {
            return;
        }

        $room->refresh()->loadMissing('players');

        $sessionIds = $room->players
            ->whereNotNull('session_id')
            ->pluck('session_id')
            ->unique()
            ->values();

        if ($sessionIds->isEmpty()) {
            return;
        }

        $existingSessions = GameSession::query()
            ->where('room_id', $room->id)
            ->whereIn('session_id', $sessionIds)
            ->pluck('session_id')
            ->all();

        $finishedAt = $room->updated_at ?? now();
        $playerCount = $room->players->count();

        $rows = $sessionIds
            ->reject(static fn (string $sessionId): bool => in_array($sessionId, $existingSessions, true))
            ->map(static fn (string $sessionId): array => [
                'session_id' => $sessionId,
                'room_id' => $room->id,
                'pack_id' => $room->pack_id,
                'finished_at' => $finishedAt,
                'player_count' => $playerCount,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows === []) {
            return;
        }

        GameSession::query()->insert($rows);
    }
}
