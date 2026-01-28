<?php

namespace Database\Factories;

use App\Domain\Game\Enums\RoomState;
use App\Models\Pack;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        // Generate code excluding O/0/I/1 for readability
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return [
            'code' => $code,
            'host_user_id' => null,
            'pack_id' => Pack::factory(),
            'state' => RoomState::Lobby,
            'current_round_id' => null,
        ];
    }

    public function withHost(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'host_user_id' => $user->id ?? User::factory(),
        ]);
    }

    public function inLobby(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => RoomState::Lobby,
        ]);
    }

    public function inRound(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => RoomState::InRound,
        ]);
    }

    public function inReveal(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => RoomState::Reveal,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => RoomState::Finished,
        ]);
    }
}
