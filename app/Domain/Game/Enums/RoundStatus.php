<?php

declare(strict_types = 1);

namespace App\Domain\Game\Enums;

enum RoundStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Revealed = 'revealed';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Revealed => 'Revealed',
            self::Done => 'Done',
        };
    }

    public function isPlayable(): bool
    {
        return $this === self::Active;
    }
}
