<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'tidal_source',
        'spotify_source',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function activeTracks(): HasMany
    {
        return $this->tracks()->where('is_active', true);
    }

    public function spotifyTracks(): HasMany
    {
        return $this->tracks()->whereHas('provider', fn ($query) => $query->where('key', 'spotify'));
    }

    public function tidalTracks(): HasMany
    {
        return $this->tracks()->whereHas('provider', fn ($query) => $query->where('key', 'tidal'));
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function getImageUrlAttribute(): string
    {
        if (! empty($this->image_path)) {
            return Storage::url($this->image_path);
        }

        return route('packs.placeholder', ['pack' => $this->id]);
    }
}
