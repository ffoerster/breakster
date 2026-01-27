<?php

namespace Database\Factories;

use App\Models\RoomPlayer;
use App\Models\TimelineItem;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimelineItem>
 */
class TimelineItemFactory extends Factory
{
    protected $model = TimelineItem::class;

    public function definition(): array
    {
        return [
            'room_player_id' => RoomPlayer::factory(),
            'track_id' => Track::factory(),
            'position' => 0,
        ];
    }

    public function atPosition(int $position): static
    {
        return $this->state(static fn(array $attributes) => [
            'position' => $position,
        ]);
    }
}
