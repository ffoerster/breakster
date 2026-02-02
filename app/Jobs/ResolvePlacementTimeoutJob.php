<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Game\GameService;
use App\Events\PlacementTimeoutResolved;
use App\Models\Round;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ResolvePlacementTimeoutJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $roundId,
    ) {}

    public function handle(GameService $gameService): void
    {
        $round = Round::find($this->roundId);

        if (! $round) {
            Log::info('ResolvePlacementTimeoutJob: Round not found', ['round_id' => $this->roundId]);

            return;
        }

        // Only process if round is still active
        if (! $round->isActive()) {
            Log::info('ResolvePlacementTimeoutJob: Round no longer active', ['round_id' => $this->roundId]);

            return;
        }

        // Only process if placement period has actually expired
        if (! $round->isPlacementPeriodExpired()) {
            Log::info('ResolvePlacementTimeoutJob: Placement period not yet expired', ['round_id' => $this->roundId]);

            return;
        }

        $autoPlacedCount = $gameService->autoPlaceMissingPlayers($round);

        if ($autoPlacedCount > 0) {
            Log::info('ResolvePlacementTimeoutJob: Auto-placed players', [
                'round_id' => $this->roundId,
                'count' => $autoPlacedCount,
            ]);

            event(new PlacementTimeoutResolved(
                $round->room->code,
                $round->id,
                $autoPlacedCount,
            ));
        }
    }
}
