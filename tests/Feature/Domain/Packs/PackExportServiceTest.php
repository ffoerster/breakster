<?php

use App\Domain\Packs\PackExportService;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Track;

it('exports a pack with all its tracks', function () {
    $pack = Pack::factory()->create([
        'name' => 'Test Pack',
        'description' => 'Test description',
        'tidal_source' => 'https://tidal.com/playlist/123',
        'spotify_source' => 'https://open.spotify.com/playlist/456',
        'is_active' => true,
    ]);

    $provider = Provider::query()->firstOrCreate(['key' => 'spotify'], ['name' => 'Spotify', 'is_active' => true]);

    Track::factory()->create([
        'pack_id' => $pack->id,
        'provider_id' => $provider->id,
        'provider_track_id' => 'abc123',
        'title' => 'Test Track',
        'artist' => 'Test Artist',
        'label' => 'Test Label',
        'release_date' => '2024-06-15',
        'artwork_url' => 'https://example.com/art.jpg',
        'preview_url' => 'https://example.com/preview.mp3',
        'external_url' => 'https://open.spotify.com/track/abc123',
        'is_active' => true,
        'difficulty' => 3,
    ]);

    $service = new PackExportService;
    $exported = $service->export($pack);

    expect($exported)->toHaveKey('version', '1.0');
    expect($exported)->toHaveKey('exported_at');
    expect($exported['pack']['name'])->toBe('Test Pack');
    expect($exported['pack']['description'])->toBe('Test description');
    expect($exported['pack']['tidal_source'])->toBe('https://tidal.com/playlist/123');
    expect($exported['pack']['spotify_source'])->toBe('https://open.spotify.com/playlist/456');
    expect($exported['pack']['is_active'])->toBeTrue();
    expect($exported['tracks'])->toHaveCount(1);
    expect($exported['tracks'][0]['provider_key'])->toBe('spotify');
    expect($exported['tracks'][0]['provider_track_id'])->toBe('abc123');
    expect($exported['tracks'][0]['title'])->toBe('Test Track');
    expect($exported['tracks'][0]['artist'])->toBe('Test Artist');
    expect($exported['tracks'][0]['label'])->toBe('Test Label');
    expect($exported['tracks'][0]['release_date'])->toBe('2024-06-15');
    expect($exported['tracks'][0]['difficulty'])->toBe(3);
});

it('generates a proper filename for export', function () {
    $pack = Pack::factory()->create(['name' => 'My Awesome Pack']);

    $service = new PackExportService;
    $filename = $service->getFilename($pack);

    expect($filename)->toStartWith('pack-my-awesome-pack-');
    expect($filename)->toEndWith('.json');
});

it('exports empty tracks array when pack has no tracks', function () {
    $pack = Pack::factory()->create();

    $service = new PackExportService;
    $exported = $service->export($pack);

    expect($exported['tracks'])->toBeArray();
    expect($exported['tracks'])->toBeEmpty();
});

it('exports multiple tracks from different providers', function () {
    $pack = Pack::factory()->create();

    $spotifyProvider = Provider::query()->firstOrCreate(['key' => 'spotify'], [
        'name' => 'Spotify',
        'is_active' => true,
    ]);

    $tidalProvider = Provider::query()->firstOrCreate(['key' => 'tidal'], ['name' => 'TIDAL', 'is_active' => true]);

    Track::factory()->create([
        'pack_id' => $pack->id,
        'provider_id' => $spotifyProvider->id,
        'provider_track_id' => 'spotify123',
        'title' => 'Spotify Track',
    ]);

    Track::factory()->create([
        'pack_id' => $pack->id,
        'provider_id' => $tidalProvider->id,
        'provider_track_id' => 'tidal456',
        'title' => 'TIDAL Track',
    ]);

    $service = new PackExportService;
    $exported = $service->export($pack);

    expect($exported['tracks'])->toHaveCount(2);

    $providerKeys = collect($exported['tracks'])->pluck('provider_key')->toArray();
    expect($providerKeys)->toContain('spotify');
    expect($providerKeys)->toContain('tidal');
});

it('generates valid JSON output', function () {
    $pack = Pack::factory()->create(['name' => 'JSON Test Pack']);

    Track::factory()->count(3)->create(['pack_id' => $pack->id]);

    $service = new PackExportService;
    $json = $service->toJson($pack);

    expect($json)->toBeString();

    $decoded = json_decode($json, true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($decoded['pack']['name'])->toBe('JSON Test Pack');
    expect($decoded['tracks'])->toHaveCount(3);
});
