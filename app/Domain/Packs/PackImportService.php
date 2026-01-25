<?php

declare(strict_types=1);

namespace App\Domain\Packs;

use App\Models\Pack;
use App\Models\Provider;
use App\Models\Track;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PackImportService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{pack: Pack, imported: int, updated: int, skipped: int}
     */
    public function import(array $data, bool $updateExisting = true): array
    {
        $this->validate($data);

        return DB::transaction(function () use ($data, $updateExisting): array {
            $pack = Pack::create([
                'name' => $data['pack']['name'],
                'description' => $data['pack']['description'] ?? null,
                'tidal_source' => $data['pack']['tidal_source'] ?? null,
                'spotify_source' => $data['pack']['spotify_source'] ?? null,
                'is_active' => $data['pack']['is_active'] ?? true,
            ]);

            $stats = $this->importTracks($pack, $data['tracks'], $updateExisting);

            return [
                'pack' => $pack,
                'imported' => $stats['imported'],
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
            ];
        });
    }

    /**
     * Import tracks into an existing pack.
     *
     * @param  array<string, mixed>  $data
     * @return array{imported: int, updated: int, skipped: int}
     */
    public function importTracksIntoPack(Pack $pack, array $data, bool $updateExisting = true): array
    {
        $this->validate($data);

        return DB::transaction(fn (): array => $this->importTracks($pack, $data['tracks'], $updateExisting));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tracks
     * @return array{imported: int, updated: int, skipped: int}
     */
    private function importTracks(Pack $pack, array $tracks, bool $updateExisting): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        $providers = Provider::query()->pluck('id', 'key');

        foreach ($tracks as $trackData) {
            $providerKey = $trackData['provider_key'];

            if (! $providers->has($providerKey)) {
                $provider = Provider::query()->firstOrCreate(
                    ['key' => $providerKey],
                    ['name' => ucfirst($providerKey), 'is_active' => true],
                );
                $providers[$providerKey] = $provider->id;
            }

            $providerId = $providers[$providerKey];

            $existing = Track::query()
                ->where('pack_id', $pack->id)
                ->where('provider_id', $providerId)
                ->where('provider_track_id', $trackData['provider_track_id'])
                ->first();

            $attributes = [
                'pack_id' => $pack->id,
                'provider_id' => $providerId,
                'provider_track_id' => $trackData['provider_track_id'],
                'title' => $trackData['title'],
                'artist' => $trackData['artist'],
                'label' => $trackData['label'] ?? null,
                'release_date' => $trackData['release_date'],
                'artwork_url' => $trackData['artwork_url'] ?? null,
                'preview_url' => $trackData['preview_url'] ?? null,
                'external_url' => $trackData['external_url'] ?? null,
                'is_active' => $trackData['is_active'] ?? true,
                'difficulty' => $trackData['difficulty'] ?? null,
            ];

            if ($existing === null) {
                Track::create($attributes);
                $imported++;
            } elseif ($updateExisting) {
                $existing->update($attributes);
                $updated++;
            } else {
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'version' => ['required', 'string'],
            'pack' => ['required', 'array'],
            'pack.name' => ['required', 'string', 'max:255'],
            'pack.description' => ['nullable', 'string'],
            'pack.tidal_source' => ['nullable', 'string', 'max:255'],
            'pack.spotify_source' => ['nullable', 'string', 'max:255'],
            'pack.is_active' => ['nullable', 'boolean'],
            'tracks' => ['required', 'array'],
            'tracks.*.provider_key' => ['required', 'string', 'in:spotify,tidal'],
            'tracks.*.provider_track_id' => ['required', 'string'],
            'tracks.*.title' => ['required', 'string', 'max:255'],
            'tracks.*.artist' => ['required', 'string', 'max:255'],
            'tracks.*.label' => ['nullable', 'string', 'max:255'],
            'tracks.*.release_date' => ['required', 'date'],
            'tracks.*.artwork_url' => ['nullable', 'string', 'max:500'],
            'tracks.*.preview_url' => ['nullable', 'string', 'max:500'],
            'tracks.*.external_url' => ['nullable', 'string', 'max:500'],
            'tracks.*.is_active' => ['nullable', 'boolean'],
            'tracks.*.difficulty' => ['nullable', 'integer', 'min:1', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function parseJsonFile(string $contents): array
    {
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: '.json_last_error_msg());
        }

        return $data;
    }
}
