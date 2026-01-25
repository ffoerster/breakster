<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Track extends Model
{
    use HasFactory;

    protected $fillable = [
        'pack_id',
        'provider_id',
        'provider_track_id',
        'title',
        'artist',
        'label',
        'release_date',
        'artwork_url',
        'preview_url',
        'external_url',
        'is_active',
        'difficulty',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'is_active' => 'boolean',
            'difficulty' => 'integer',
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function timelineItems(): HasMany
    {
        return $this->hasMany(TimelineItem::class);
    }

    public function usedInRooms(): HasMany
    {
        return $this->hasMany(RoomUsedTrack::class);
    }

    public function hasPreview(): bool
    {
        return $this->preview_url !== null;
    }

    public function getPlayUrl(): ?string
    {
        return $this->preview_url ?? $this->external_url;
    }

    public function externalUrlForProvider(Provider $provider): string
    {
        $searchQuery = urlencode("{$this->artist} {$this->title}");

        return match ($provider->key) {
            'spotify' => "https://open.spotify.com/search/{$searchQuery}",
            'tidal' => "https://tidal.com/search?q={$searchQuery}",
            default => $this->external_url ?? "https://www.google.com/search?q={$searchQuery}",
        };
    }
}
