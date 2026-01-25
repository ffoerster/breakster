<?php

namespace Database\Seeders;

use App\Models\Pack;
use App\Models\Track;
use Illuminate\Database\Seeder;

class PackSeeder extends Seeder
{
    public function run(): void
    {
        // Create a main pack with 200 tracks spanning many decades
        $pack = Pack::create([
            'name' => 'Ultimate Music Quiz',
            'description' => 'A diverse collection of hits spanning from the 1960s to today. Perfect for testing your music knowledge across all eras.',
            'is_active' => true,
        ]);

        // Generate tracks across different decades
        $decades = [
            ['start' => 1960, 'end' => 1969, 'count' => 20],
            ['start' => 1970, 'end' => 1979, 'count' => 25],
            ['start' => 1980, 'end' => 1989, 'count' => 35],
            ['start' => 1990, 'end' => 1999, 'count' => 35],
            ['start' => 2000, 'end' => 2009, 'count' => 35],
            ['start' => 2010, 'end' => 2019, 'count' => 30],
            ['start' => 2020, 'end' => 2024, 'count' => 20],
        ];

        foreach ($decades as $decade) {
            for ($i = 0; $i < $decade['count']; $i++) {
                Track::factory()
                    ->for($pack)
                    ->create([
                        'release_date' => fake()->dateTimeBetween(
                            "{$decade['start']}-01-01",
                            "{$decade['end']}-12-31"
                        ),
                    ]);
            }
        }

        // Create a smaller 80s-focused pack
        $eightiesPack = Pack::create([
            'name' => '80s Classics',
            'description' => 'The best of the 1980s - synth-pop, rock, and everything in between.',
            'is_active' => true,
        ]);

        Track::factory()
            ->count(50)
            ->for($eightiesPack)
            ->create([
                'release_date' => fn () => fake()->dateTimeBetween('1980-01-01', '1989-12-31'),
            ]);

        // Create a 2000s pack
        $millenniumPack = Pack::create([
            'name' => 'Y2K Hits',
            'description' => 'Relive the early 2000s with these iconic tracks.',
            'is_active' => true,
        ]);

        Track::factory()
            ->count(50)
            ->for($millenniumPack)
            ->create([
                'release_date' => fn () => fake()->dateTimeBetween('2000-01-01', '2009-12-31'),
            ]);

        $this->command->info('Created 3 packs with a total of 300 tracks.');
    }
}
