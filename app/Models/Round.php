<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Game\Enums\RoundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Round extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'track_id',
        'round_number',
        'status',
        'started_at',
        'placement_unlocks_at',
        'placement_ends_at',
        'revealed_at',
        'amen_claimed_by_room_player_id',
        'amen_claimed_at',
        'amen_awarded_points',
        'amen_resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoundStatus::class,
            'round_number' => 'integer',
            'started_at' => 'datetime',
            'placement_unlocks_at' => 'datetime',
            'placement_ends_at' => 'datetime',
            'revealed_at' => 'datetime',
            'amen_claimed_at' => 'datetime',
            'amen_awarded_points' => 'integer',
            'amen_resolved_at' => 'datetime',
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

    public function amenClaimant(): BelongsTo
    {
        return $this->belongsTo(RoomPlayer::class, 'amen_claimed_by_room_player_id');
    }

    public function amenGuess(): HasOne
    {
        return $this->hasOne(AmenGuess::class);
    }

    public function amenVotes(): HasMany
    {
        return $this->hasMany(AmenVote::class);
    }

    public function isActive(): bool
    {
        return $this->status === RoundStatus::Active;
    }

    public function isRevealed(): bool
    {
        return $this->revealed_at !== null || $this->status === RoundStatus::Revealed;
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

    public function hasAmenClaim(): bool
    {
        return $this->amen_claimed_by_room_player_id !== null;
    }

    public function isAmenResolved(): bool
    {
        return $this->amen_resolved_at !== null;
    }

    public function canClaimAmen(): bool
    {
        return $this->isActive() && ! $this->hasAmenClaim();
    }

    public function isPlacementPeriodExpired(): bool
    {
        return $this->placement_ends_at !== null && now()->gte($this->placement_ends_at);
    }
}
