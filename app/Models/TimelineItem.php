<?php

declare(strict_types = 1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimelineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_player_id',
        'track_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(RoomPlayer::class, 'room_player_id');
    }

    public function roomPlayer(): BelongsTo
    {
        return $this->player();
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
