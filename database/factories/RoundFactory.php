<?php

namespace Database\Factories;

use App\Domain\Game\Enums\RoundStatus;
use App\Models\Room;
use App\Models\Round;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
{
    protected $model = Round::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'track_id' => Track::factory(),
            'round_number' => 1,
            'status' => RoundStatus::Pending,
            'started_at' => null,
            'revealed_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(static fn(array $attributes) => [
            'status' => RoundStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function revealed(): static
    {
        return $this->state(static fn(array $attributes) => [
            'status' => RoundStatus::Revealed,
            'started_at' => now()->subMinutes(2),
            'revealed_at' => now(),
        ]);
    }

    public function done(): static
    {
        return $this->state(static fn(array $attributes) => [
            'status' => RoundStatus::Done,
            'started_at' => now()->subMinutes(5),
            'revealed_at' => now()->subMinutes(2),
        ]);
    }

    public function roundNumber(int $number): static
    {
        return $this->state(static fn(array $attributes) => [
            'round_number' => $number,
        ]);
    }
}
