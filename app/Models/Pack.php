<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'tidal_source',
        'spotify_source',
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

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
