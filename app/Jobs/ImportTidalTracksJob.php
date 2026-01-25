<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Providers\Tidal\TidalClient;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportTidalTracksJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $packId,
        public string $source,
    ) {}

    public function handle(TidalClient $tidal): void
    {
        $pack = Pack::findOrFail($this->packId);
        $normalized = $tidal->fetchTracks($this->source);

        $provider = Provider::query()->firstOrCreate(
            ['key' => 'tidal'],
            ['name' => 'TIDAL', 'is_active' => true],
        );
        $providerTrackIds = collect($normalized)
            ->pluck('provider_track_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $existing = Track::query()
            ->where('pack_id', $pack->id)
            ->where('provider_id', $provider->id)
            ->whereIn('provider_track_id', $providerTrackIds)
            ->get()
            ->keyBy('provider_track_id');

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($normalized as $row) {
            $providerTrackId = (string) $row['provider_track_id'];
            $attributes = [
                'pack_id' => $pack->id,
                'provider_id' => $provider->id,
                'provider_track_id' => $providerTrackId,
                'title' => $row['title'],
                'artist' => $row['artist'],
                'label' => $row['label'] ?? null,
                'release_date' => $row['release_date'],
                'artwork_url' => $row['artwork_url'],
                'preview_url' => $row['preview_url'],
                'external_url' => $row['external_url'],
                'is_active' => true,
            ];

            /** @var Track|null $current */
            $current = $existing->get($providerTrackId);

            if (! $current) {
                Track::create($attributes);
                $imported++;

                continue;
            }

            $dirty = false;
            foreach (['title', 'artist', 'label', 'release_date', 'artwork_url', 'preview_url', 'external_url'] as $field) {
                if ($current->getAttribute($field) !== $attributes[$field]) {
                    $dirty = true;
                    break;
                }
            }

            if (! $dirty) {
                $skipped++;

                continue;
            }

            $current->fill($attributes);
            $current->save();
            $updated++;
        }

        $pack->update([
            'tidal_source' => $this->source,
        ]);

        Log::info('TIDAL import completed', [
            'pack_id' => $pack->id,
            'provider' => $provider->key,
            'source' => $this->source,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($normalized),
        ]);
    }
}
