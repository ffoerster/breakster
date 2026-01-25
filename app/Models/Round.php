<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Game\Enums\RoundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'track_id',
        'round_number',
        'status',
        'started_at',
        'revealed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoundStatus::class,
            'round_number' => 'integer',
            'started_at' => 'datetime',
            'revealed_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function isActive(): bool
    {
        return $this->status === RoundStatus::Active;
    }

    public function isRevealed(): bool
    {
        return $this->status === RoundStatus::Revealed;
    }

    public function allPlayersSubmitted(): bool
    {
        $playerCount = $this->room->players()->count();
        $placementCount = $this->placements()->count();

        return $placementCount >= $playerCount;
    }

    public function getPlacementForPlayer(RoomPlayer $player): ?Placement
    {
        return $this->placements()->where('room_player_id', $player->id)->first();
    }
}
