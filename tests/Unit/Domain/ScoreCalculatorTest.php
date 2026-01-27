<?php

use App\Domain\Game\ScoreCalculator;

describe('ScoreCalculator', function () {
    it('returns 0 points for incorrect placement', function () {
        $calculator = new ScoreCalculator;

        expect($calculator->calculatePoints(isCorrect: false, secondsToSubmit: 0))->toBe(0);
        expect($calculator->calculatePoints(isCorrect: false, secondsToSubmit: 10))->toBe(0);
        expect($calculator->calculatePoints(isCorrect: false, secondsToSubmit: 100))->toBe(0);
    });

    it('returns base points plus speed bonus for correct placement', function () {
        $calculator = new ScoreCalculator;

        // Instant submission gets full bonus
        $points = $calculator->calculatePoints(isCorrect: true, secondsToSubmit: 0);

        expect($points)->toBe(ScoreCalculator::CORRECT_PLACEMENT_POINTS + ScoreCalculator::SPEED_BONUS_MAX);
    });

    it('returns base points without speed bonus after window expires', function () {
        $calculator = new ScoreCalculator;

        $points = $calculator->calculatePoints(
            isCorrect: true,
            secondsToSubmit: ScoreCalculator::SPEED_BONUS_WINDOW_SECONDS,
        );

        expect($points)->toBe(ScoreCalculator::CORRECT_PLACEMENT_POINTS);
    });

    it('calculates max speed bonus for instant submission', function () {
        $calculator = new ScoreCalculator;

        expect($calculator->calculateSpeedBonus(0))->toBe(ScoreCalculator::SPEED_BONUS_MAX);
    });

    it('calculates zero speed bonus after window expires', function () {
        $calculator = new ScoreCalculator;

        expect($calculator->calculateSpeedBonus(ScoreCalculator::SPEED_BONUS_WINDOW_SECONDS))->toBe(0);
        expect($calculator->calculateSpeedBonus(60))->toBe(0);
        expect($calculator->calculateSpeedBonus(120))->toBe(0);
    });

    it('calculates speed bonus linearly within window', function () {
        $calculator = new ScoreCalculator;

        // At halfway point, should get half the bonus
        $halfwaySeconds = ScoreCalculator::SPEED_BONUS_WINDOW_SECONDS / 2;
        $halfwayBonus = $calculator->calculateSpeedBonus((int) $halfwaySeconds);

        expect($halfwayBonus)->toBe((int) round(ScoreCalculator::SPEED_BONUS_MAX / 2));
    });
});
