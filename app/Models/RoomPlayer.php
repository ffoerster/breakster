<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class RoomPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'display_name',
        'session_id',
        'score',
        'is_host',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'is_host' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function timelineItems(): HasMany
    {
        return $this->hasMany(TimelineItem::class);
    }

    public function isHost(): bool
    {
        return $this->is_host;
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function timeline(): Collection
    {
        return $this->timelineItems()
            ->with('track')
            ->orderBy('position')
            ->get();
    }

    public function hasPlacementForRound(Round $round): bool
    {
        return $this->placements()->where('round_id', $round->id)->exists();
    }

    public function addScore(int $points): void
    {
        $this->increment('score', $points);
    }
}
