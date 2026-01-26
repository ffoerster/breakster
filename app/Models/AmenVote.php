<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmenVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_id',
        'voter_room_player_id',
        'vote',
    ];

    protected function casts(): array
    {
        return [
            'vote' => 'boolean',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(RoomPlayer::class, 'voter_room_player_id');
    }
}
