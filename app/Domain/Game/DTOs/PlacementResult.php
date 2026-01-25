<?php

declare(strict_types=1);

namespace App\Domain\Game\DTOs;

use App\Models\Placement;

readonly class PlacementResult
{
    public function __construct(
        public Placement $placement,
        public bool $isCorrect,
        public int $pointsEarned,
        public ?string $message = null,
    ) {}

    public static function correct(Placement $placement, int $points): self
    {
        return new self(
            placement: $placement,
            isCorrect: true,
            pointsEarned: $points,
            message: 'Correct placement!',
        );
    }

    public static function incorrect(Placement $placement): self
    {
        return new self(
            placement: $placement,
            isCorrect: false,
            pointsEarned: 0,
            message: 'Incorrect placement.',
        );
    }
}
