<?php

declare(strict_types = 1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Placement extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_id',
        'room_player_id',
        'inserted_after_track_id',
        'is_correct',
        'points_earned',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'points_earned' => 'integer',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(RoomPlayer::class, 'room_player_id');
    }

    public function insertedAfterTrack(): BelongsTo
    {
        return $this->belongsTo(Track::class, 'inserted_after_track_id');
    }

    public function isAtBeginning(): bool
    {
        return $this->inserted_after_track_id === null;
    }

    public function wasCorrect(): bool
    {
        return $this->is_correct === true;
    }
}
