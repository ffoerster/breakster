<?php

declare(strict_types=1);

namespace App\Domain\Game\DTOs;

use App\Models\Round;
use App\Models\Track;
use Illuminate\Support\Collection;

readonly class RoundSummary
{
    /**
     * @param  Collection<int, PlacementResult>  $results  Results keyed by room_player_id
     */
    public function __construct(
        public Round $round,
        public Track $track,
        public Collection $results,
        public int $correctCount,
        public int $incorrectCount,
    ) {}

    public static function fromRound(Round $round, Collection $results): self
    {
        $correctCount = $results->filter(fn (PlacementResult $r) => $r->isCorrect)->count();

        return new self(
            round: $round,
            track: $round->track,
            results: $results,
            correctCount: $correctCount,
            incorrectCount: $results->count() - $correctCount,
        );
    }

    public function getResultForPlayer(int $roomPlayerId): ?PlacementResult
    {
        return $this->results->get($roomPlayerId);
    }
}
