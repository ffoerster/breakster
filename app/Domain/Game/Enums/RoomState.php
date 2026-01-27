<?php

declare(strict_types = 1);

namespace App\Domain\Game\Enums;

enum RoomState: string
{
    case Lobby = 'lobby';
    case InRound = 'in_round';
    case Reveal = 'reveal';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Lobby => 'Lobby',
            self::InRound => 'In Round',
            self::Reveal => 'Reveal',
            self::Finished => 'Finished',
        };
    }

    public function canStartRound(): bool
    {
        return $this === self::Lobby || $this === self::Reveal;
    }

    public function canSubmitPlacement(): bool
    {
        return $this === self::InRound;
    }
}
