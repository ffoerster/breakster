<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundStarted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $roomCode,
        public int $roundId,
        public int $roundNumber,
        public ?\Illuminate\Support\Carbon $placementUnlocksAt = null,
        public ?\Illuminate\Support\Carbon $placementEndsAt = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("room.{$this->roomCode}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'round.started';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'roomCode' => $this->roomCode,
            'roundId' => $this->roundId,
            'roundNumber' => $this->roundNumber,
            'placementUnlocksAt' => $this->placementUnlocksAt?->toIso8601String(),
            'placementEndsAt' => $this->placementEndsAt?->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
