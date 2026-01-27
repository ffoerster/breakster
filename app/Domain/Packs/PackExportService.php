<?php

declare(strict_types = 1);

namespace App\Domain\Packs;

use App\Models\Pack;
use Illuminate\Support\Carbon;

class PackExportService
{
    /**
     * @return array{
     *     version: string,
     *     exported_at: string,
     *     pack: array{name: string, description: string|null, tidal_source: string|null, spotify_source: string|null, is_active: bool},
     *     tracks: array<int, array{provider_key: string, provider_track_id: string, title: string, artist: string, label: string|null, release_date: string, artwork_url: string|null, preview_url: string|null, external_url: string|null, is_active: bool, difficulty: int|null}>
     * }
     */
    public function export(Pack $pack): array
    {
        $pack->load('tracks.provider');

        return [
            'version' => '1.0',
            'exported_at' => Carbon::now()->toIso8601String(),
            'pack' => [
                'name' => $pack->name,
                'description' => $pack->description,
                'tidal_source' => $pack->tidal_source,
                'spotify_source' => $pack->spotify_source,
                'is_active' => $pack->is_active,
            ],
            'tracks' => $pack
                ->tracks
                ->map(static fn($track) => [
                    'provider_key' => $track->provider->key,
                    'provider_track_id' => $track->provider_track_id,
                    'title' => $track->title,
                    'artist' => $track->artist,
                    'label' => $track->label,
                    'release_date' => $track->release_date->format('Y-m-d'),
                    'artwork_url' => $track->artwork_url,
                    'preview_url' => $track->preview_url,
                    'external_url' => $track->external_url,
                    'is_active' => $track->is_active,
                    'difficulty' => $track->difficulty,
                ])
                ->values()
                ->all(),
        ];
    }

    public function toJson(Pack $pack): string
    {
        return json_encode($this->export($pack), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getFilename(Pack $pack): string
    {
        $slug = str($pack->name)->slug();
        $date = now()->format('Y-m-d');

        return "pack-{$slug}-{$date}.json";
    }
}
