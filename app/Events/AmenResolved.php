<?php

declare(strict_types = 1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AmenResolved implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{artist: string, title: string}|null  $guess
     * @param  array{artist: string, title: string}|null  $actual
     */
    public function __construct(
        public string $roomCode,
        public int $roundId,
        public bool $wasCorrect,
        public int $pointsAwarded,
        public ?array $guess,
        public ?array $actual,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("room.{$this->roomCode}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'amen.resolved';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'roomCode' => $this->roomCode,
            'roundId' => $this->roundId,
            'wasCorrect' => $this->wasCorrect,
            'pointsAwarded' => $this->pointsAwarded,
            'guess' => $this->guess,
            'actual' => $this->actual,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
