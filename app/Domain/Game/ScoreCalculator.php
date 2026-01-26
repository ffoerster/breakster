<?php

declare(strict_types=1);

namespace App\Domain\Game;

class ScoreCalculator
{
    /**
     * Base points awarded for a correct placement.
     */
    public const CORRECT_PLACEMENT_POINTS = 10;

    /**
     * Maximum speed bonus points.
     */
    public const SPEED_BONUS_MAX = 5;

    /**
     * Window in seconds for speed bonus (bonus decreases linearly).
     */
    public const SPEED_BONUS_WINDOW_SECONDS = 30;

    /**
     * Bonus points awarded for a correct AMEN claim (majority vote yes).
     */
    public const AMEN_BONUS_POINTS = 2;

    /**
     * Penalty points for an incorrect AMEN claim (majority vote no).
     */
    public const AMEN_PENALTY_POINTS = -1;

    /**
     * Calculate total points for a placement.
     *
     * @param  bool  $isCorrect  Whether the placement was correct
     * @param  int  $secondsToSubmit  Time taken to submit (from round start)
     * @return int Total points earned
     */
    public function calculatePoints(bool $isCorrect, int $secondsToSubmit = 0): int
    {
        if (! $isCorrect) {
            return 0;
        }

        return self::CORRECT_PLACEMENT_POINTS + $this->calculateSpeedBonus($secondsToSubmit);
    }

    /**
     * Calculate speed bonus based on how quickly the player submitted.
     *
     * Bonus scales linearly from SPEED_BONUS_MAX (instant) to 0 (at or after SPEED_BONUS_WINDOW_SECONDS).
     *
     * @param  int  $secondsToSubmit  Time in seconds from round start to submission
     * @return int Speed bonus points (0 to SPEED_BONUS_MAX)
     */
    public function calculateSpeedBonus(int $secondsToSubmit): int
    {
        if ($secondsToSubmit <= 0) {
            return self::SPEED_BONUS_MAX;
        }

        if ($secondsToSubmit >= self::SPEED_BONUS_WINDOW_SECONDS) {
            return 0;
        }

        // Linear interpolation: bonus decreases as time increases
        $ratio = 1 - ($secondsToSubmit / self::SPEED_BONUS_WINDOW_SECONDS);

        return (int) round(self::SPEED_BONUS_MAX * $ratio);
    }
}
