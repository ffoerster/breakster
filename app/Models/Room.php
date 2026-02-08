<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Game\Enums\RoomState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'host_user_id',
        'pack_id',
        'playback_provider_id',
        'spotify_device_id',
        'state',
        'placement_timer_enabled',
        'placement_timer_duration',
        'placement_delay_seconds',
        'max_rounds',
        'amen_enabled',
        'current_round_id',
    ];

    protected function casts(): array
    {
        return [
            'state' => RoomState::class,
            'placement_timer_enabled' => 'boolean',
            'amen_enabled' => 'boolean',
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    public function playbackProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'playback_provider_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function usedTracks(): HasMany
    {
        return $this->hasMany(RoomUsedTrack::class);
    }

    public function currentRound(): BelongsTo
    {
        return $this->belongsTo(Round::class, 'current_round_id');
    }

    public function hostPlayer(): ?RoomPlayer
    {
        return $this->players()->where('is_host', true)->first();
    }

    public function isInLobby(): bool
    {
        return $this->state === RoomState::Lobby;
    }

    public function isInRound(): bool
    {
        return $this->state === RoomState::InRound;
    }

    public function isInReveal(): bool
    {
        return $this->state === RoomState::Reveal;
    }

    public function isFinished(): bool
    {
        return $this->state === RoomState::Finished;
    }

    public function usedTrackIds(): array
    {
        return $this->usedTracks()->pluck('track_id')->toArray();
    }
}
