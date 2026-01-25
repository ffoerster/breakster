<?php

use App\Domain\Packs\PackImportService;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Track;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Provider::query()->firstOrCreate(
        ['key' => 'spotify'],
        ['name' => 'Spotify', 'is_active' => true],
    );

    Provider::query()->firstOrCreate(
        ['key' => 'tidal'],
        ['name' => 'TIDAL', 'is_active' => true],
    );
});

it('imports a pack with tracks from valid JSON data', function () {
    $data = [
        'version' => '1.0',
        'exported_at' => '2026-01-25T12:00:00Z',
        'pack' => [
            'name' => 'Imported Pack',
            'description' => 'Test description',
            'tidal_source' => null,
            'spotify_source' => 'https://open.spotify.com/playlist/123',
            'is_active' => true,
        ],
        'tracks' => [
            [
                'provider_key' => 'spotify',
                'provider_track_id' => 'track123',
                'title' => 'Test Song',
                'artist' => 'Test Artist',
                'label' => 'Test Label',
                'release_date' => '2024-01-15',
                'artwork_url' => 'https://example.com/art.jpg',
                'preview_url' => null,
                'external_url' => 'https://open.spotify.com/track/track123',
                'is_active' => true,
                'difficulty' => 3,
            ],
        ],
    ];

    $service = new PackImportService;
    $result = $service->import($data);

    expect($result['pack'])->toBeInstanceOf(Pack::class);
    expect($result['pack']->name)->toBe('Imported Pack');
    expect($result['pack']->description)->toBe('Test description');
    expect($result['pack']->spotify_source)->toBe('https://open.spotify.com/playlist/123');
    expect($result['imported'])->toBe(1);
    expect($result['updated'])->toBe(0);
    expect($result['skipped'])->toBe(0);

    $track = Track::query()->where('pack_id', $result['pack']->id)->first();
    expect($track->title)->toBe('Test Song');
    expect($track->artist)->toBe('Test Artist');
    expect($track->difficulty)->toBe(3);
});

it('imports tracks into an existing pack', function () {
    $pack = Pack::factory()->create(['name' => 'Existing Pack']);

    $data = [
        'version' => '1.0',
        'pack' => ['name' => 'Ignored Pack Name', 'is_active' => true],
        'tracks' => [
            [
                'provider_key' => 'spotify',
                'provider_track_id' => 'new-track-1',
                'title' => 'New Track 1',
                'artist' => 'Artist 1',
                'release_date' => '2024-01-01',
            ],
            [
                'provider_key' => 'tidal',
                'provider_track_id' => 'new-track-2',
                'title' => 'New Track 2',
                'artist' => 'Artist 2',
                'release_date' => '2024-02-01',
            ],
        ],
    ];

    $service = new PackImportService;
    $result = $service->importTracksIntoPack($pack, $data);

    expect($result['imported'])->toBe(2);
    expect($pack->tracks()->count())->toBe(2);
    expect($pack->fresh()->name)->toBe('Existing Pack');
});

it('skips duplicate tracks when update_existing is false', function () {
    $pack = Pack::factory()->create();
    $provider = Provider::query()->where('key', 'spotify')->first();

    Track::factory()->create([
        'pack_id' => $pack->id,
        'provider_id' => $provider->id,
        'provider_track_id' => 'existing123',
        'title' => 'Original Title',
    ]);

    $data = [
        'version' => '1.0',
        'pack' => ['name' => 'Test', 'is_active' => true],
        'tracks' => [
            [
                'provider_key' => 'spotify',
                'provider_track_id' => 'existing123',
                'title' => 'New Title',
                'artist' => 'Artist',
                'release_date' => '2024-01-01',
            ],
        ],
    ];

    $service = new PackImportService;
    $result = $service->importTracksIntoPack($pack, $data, updateExisting: false);

    expect($result['skipped'])->toBe(1);
    expect($result['updated'])->toBe(0);
    expect($result['imported'])->toBe(0);

    $track = Track::query()
        ->where('pack_id', $pack->id)
        ->where('provider_track_id', 'existing123')
        ->first();
    expect($track->title)->toBe('Original Title');
});

it('updates existing tracks when update_existing is true', function () {
    $pack = Pack::factory()->create();
    $provider = Provider::query()->where('key', 'spotify')->first();

    Track::factory()->create([
        'pack_id' => $pack->id,
        'provider_id' => $provider->id,
        'provider_track_id' => 'existing123',
        'title' => 'Original Title',
        'artist' => 'Original Artist',
    ]);

    $data = [
        'version' => '1.0',
        'pack' => ['name' => 'Test', 'is_active' => true],
        'tracks' => [
            [
                'provider_key' => 'spotify',
                'provider_track_id' => 'existing123',
                'title' => 'Updated Title',
                'artist' => 'Updated Artist',
                'release_date' => '2024-01-01',
            ],
        ],
    ];

    $service = new PackImportService;
    $result = $service->importTracksIntoPack($pack, $data, updateExisting: true);

    expect($result['updated'])->toBe(1);
    expect($result['imported'])->toBe(0);

    $track = Track::query()
        ->where('pack_id', $pack->id)
        ->where('provider_track_id', 'existing123')
        ->first();
    expect($track->title)->toBe('Updated Title');
    expect($track->artist)->toBe('Updated Artist');
});

it('throws validation exception for missing pack name', function () {
    $data = [
        'version' => '1.0',
        'pack' => ['name' => '', 'is_active' => true],
        'tracks' => [],
    ];

    $service = new PackImportService;
    $service->validate($data);
})->throws(ValidationException::class);

it('throws validation exception for invalid provider key', function () {
    $data = [
        'version' => '1.0',
        'pack' => ['name' => 'Test Pack', 'is_active' => true],
        'tracks' => [
            [
                'provider_key' => 'invalid_provider',
                'provider_track_id' => 'track123',
                'title' => 'Test',
                'artist' => 'Artist',
                'release_date' => '2024-01-01',
            ],
        ],
    ];

    $service = new PackImportService;
    $service->validate($data);
})->throws(ValidationException::class);

it('throws exception for invalid JSON', function () {
    $service = new PackImportService;
    $service->parseJsonFile('not valid json {{{');
})->throws(InvalidArgumentException::class);

it('parses valid JSON correctly', function () {
    $service = new PackImportService;
    $result = $service->parseJsonFile('{"version": "1.0", "pack": {"name": "Test"}, "tracks": []}');

    expect($result)->toBeArray();
    expect($result['version'])->toBe('1.0');
    expect($result['pack']['name'])->toBe('Test');
});

it('handles mixed new and existing tracks correctly', function () {
    $pack = Pack::factory()->create();
    $provider = Provider::query()->where('key', 'spotify')->first();

    Track::factory()->create([
        'pack_id' => $pack->id,
        'provider_id' => $provider->id,
        'provider_track_id' => 'existing-track',
        'title' => 'Existing Track',
    ]);

    $data = [
        'version' => '1.0',
        'pack' => ['name' => 'Test', 'is_active' => true],
        'tracks' => [
            [
                'provider_key' => 'spotify',
                'provider_track_id' => 'existing-track',
                'title' => 'Updated Existing Track',
                'artist' => 'Artist',
                'release_date' => '2024-01-01',
            ],
            [
                'provider_key' => 'spotify',
                'provider_track_id' => 'new-track',
                'title' => 'New Track',
                'artist' => 'Artist',
                'release_date' => '2024-01-01',
            ],
        ],
    ];

    $service = new PackImportService;
    $result = $service->importTracksIntoPack($pack, $data, updateExisting: true);

    expect($result['imported'])->toBe(1);
    expect($result['updated'])->toBe(1);
    expect($result['skipped'])->toBe(0);
    expect($pack->tracks()->count())->toBe(2);
});

it('imports tracks with null optional fields', function () {
    $data = [
        'version' => '1.0',
        'pack' => ['name' => 'Minimal Pack', 'is_active' => true],
        'tracks' => [
            [
                'provider_key' => 'spotify',
                'provider_track_id' => 'minimal-track',
                'title' => 'Minimal Track',
                'artist' => 'Artist',
                'release_date' => '2024-01-01',
                'label' => null,
                'artwork_url' => null,
                'preview_url' => null,
                'external_url' => null,
                'difficulty' => null,
            ],
        ],
    ];

    $service = new PackImportService;
    $result = $service->import($data);

    expect($result['imported'])->toBe(1);

    $track = Track::query()->where('pack_id', $result['pack']->id)->first();
    expect($track->label)->toBeNull();
    expect($track->artwork_url)->toBeNull();
    expect($track->difficulty)->toBeNull();
});
