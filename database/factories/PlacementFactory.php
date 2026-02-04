<?php

namespace Database\Factories;

use App\Models\Placement;
use App\Models\RoomPlayer;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Placement>
 */
class PlacementFactory extends Factory
{
    protected $model = Placement::class;

    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'room_player_id' => RoomPlayer::factory(),
            'inserted_after_track_id' => null,
            'is_correct' => null,
            'points_earned' => 0,
        ];
    }

    public function correct(int $points = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
            'points_earned' => $points,
        ]);
    }

    public function incorrect(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => false,
            'points_earned' => 0,
        ]);
    }

    public function afterTrack(int $trackId): static
    {
        return $this->state(fn (array $attributes) => [
            'inserted_after_track_id' => $trackId,
        ]);
    }
}
