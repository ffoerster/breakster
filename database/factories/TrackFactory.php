<?php

namespace Database\Factories;

use App\Models\Pack;
use App\Models\Provider;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    protected $model = Track::class;

    public function definition(): array
    {
        $artists = [
            'The Beatles', 'Queen', 'Michael Jackson', 'Madonna', 'Prince',
            'David Bowie', 'Elton John', 'Whitney Houston', 'Stevie Wonder', 'ABBA',
            'Fleetwood Mac', 'The Rolling Stones', 'Led Zeppelin', 'Pink Floyd', 'U2',
            'Nirvana', 'Oasis', 'Radiohead', 'Coldplay', 'Arctic Monkeys',
            'Beyoncé', 'Adele', 'Ed Sheeran', 'Taylor Swift', 'Bruno Mars',
            'Daft Punk', 'The Weeknd', 'Kendrick Lamar', 'Drake', 'Rihanna',
        ];

        $songPrefixes = ['Love', 'Dance', 'Dream', 'Night', 'Summer', 'Fire', 'Heart', 'Soul', 'Wild', 'Free'];
        $songSuffixes = ['Song', 'Dreams', 'Nights', 'Dance', 'Love', 'Heart', 'Away', 'Forever', 'Tonight', 'Baby'];

        return [
            'pack_id' => Pack::factory(),
            'provider_id' => Provider::query()->firstOrCreate(
                ['key' => 'tidal'],
                ['name' => 'TIDAL', 'is_active' => true],
            )->id,
            'provider_track_id' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'title' => fake()->randomElement($songPrefixes).' '.fake()->randomElement($songSuffixes),
            'artist' => fake()->randomElement($artists),
            'label' => fake()->optional(0.4)->company(),
            'release_date' => fake()->dateTimeBetween('1960-01-01', '2024-12-31'),
            'artwork_url' => 'https://picsum.photos/seed/'.fake()->uuid().'/300/300',
            'preview_url' => null,
            'external_url' => 'https://tidal.com/browse/track/'.fake()->numberBetween(100000, 999999999),
            'is_active' => true,
            'difficulty' => fake()->numberBetween(1, 5),
        ];
    }

    public function withPreview(): static
    {
        return $this->state(fn (array $attributes) => [
            'preview_url' => 'https://preview.tidal.com/track/'.$attributes['provider_track_id'].'.mp3',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function fromYear(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'release_date' => fake()->dateTimeBetween("$year-01-01", "$year-12-31"),
        ]);
    }
}
