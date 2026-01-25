<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Models\Room;

class RoomCodeGenerator
{
    /**
     * Characters to use for room codes.
     * Excludes O/0/I/1 for readability.
     */
    private const CHARACTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 6;

    private const MAX_ATTEMPTS = 10;

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $this->generateCode();

            if (! $this->codeExists($code)) {
                return $code;
            }
        }

        // If we've exhausted attempts, try with longer code
        return $this->generateCode(8);
    }

    private function generateCode(int $length = self::CODE_LENGTH): string
    {
        $code = '';
        $maxIndex = strlen(self::CHARACTERS) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= self::CHARACTERS[random_int(0, $maxIndex)];
        }

        return $code;
    }

    private function codeExists(string $code): bool
    {
        return Room::where('code', $code)->exists();
    }
}
