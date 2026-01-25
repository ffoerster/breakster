<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomPlayer>
 */
class RoomPlayerFactory extends Factory
{
    protected $model = RoomPlayer::class;

    public function definition(): array
    {
        $nicknames = [
            'CoolCat', 'MusicMaster', 'BeatDrop', 'VinylVibes', 'TrackStar',
            'MelodyMaker', 'RhythmKing', 'SongSleuth', 'TuneHunter', 'GroovyGuru',
            'NoteNinja', 'ChordChaser', 'TempoTitan', 'HarmonyHero', 'SoundSeeker',
        ];

        return [
            'room_id' => Room::factory(),
            'user_id' => null,
            'display_name' => fake()->randomElement($nicknames).fake()->numberBetween(1, 999),
            'session_id' => fake()->uuid(),
            'score' => 0,
            'is_host' => false,
            'joined_at' => now(),
        ];
    }

    public function withUser(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            $user = $user ?? User::factory()->create();

            return [
                'user_id' => $user->id,
                'display_name' => $user->name,
                'session_id' => null,
            ];
        });
    }

    public function asHost(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_host' => true,
        ]);
    }

    public function withScore(int $score): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $score,
        ]);
    }
}
