<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(1),
            'name' => fake()->company(),
            'is_active' => true,
        ];
    }

    public function tidal(): static
    {
        return $this->state([
            'key' => 'tidal',
            'name' => 'TIDAL',
            'is_active' => true,
        ]);
    }
}
