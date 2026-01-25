<?php

namespace Database\Factories;

use App\Models\Pack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pack>
 */
class PackFactory extends Factory
{
    protected $model = Pack::class;

    public function definition(): array
    {
        $genres = ['Pop Hits', 'Rock Classics', 'Hip Hop Bangers', '80s Essentials', '90s Nostalgia', 'Indie Favorites', 'R&B Smooth', 'Country Roads', 'Electronic Beats', 'Jazz Standards'];

        return [
            'name' => fake()->randomElement($genres).' '.fake()->year(),
            'description' => fake()->sentence(10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
