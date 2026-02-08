<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Models\Placement;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Support\Facades\DB;

class GameSummaryService
{
    /**
     * Get complete game summary for a finished room.
     *
     * @return array{
     *     standings: array<int, array>,
     *     awards: array{most_accurate: array|null, biggest_miss: array|null},
     *     amen_stats: array{total_claims: int, by_player: array},
     *     game_stats: array{rounds_played: int, total_placements: int, overall_accuracy: int}
     * }
     */
    public function getSummary(Room $room, ?RoomPlayer $currentPlayer = null): array
    {
        return [
            'standings' => $this->getFinalStandings($room, $currentPlayer),
            'awards' => [
                'most_accurate' => $this->getMostAccuratePlayer($room),
                'biggest_miss' => $this->getBiggestMiss($room),
            ],
            'amen_stats' => $this->getAmenStats($room),
            'game_stats' => $this->getGameStats($room),
        ];
    }

    /**
     * Get final standings with ranking and stats per player.
     *
     * @return array<int, array{
     *     rank: int,
     *     player_id: int,
     *     display_name: string,
     *     score: int,
     *     is_me: bool,
     *     correct_placements: int,
     *     total_placements: int,
     *     accuracy_percentage: int
     * }>
     */
    public function getFinalStandings(Room $room, ?RoomPlayer $currentPlayer = null): array
    {
        $players = $room->players()
            ->orderByDesc('score')
            ->orderBy('joined_at')
            ->get();

        $roundIds = $room->rounds()->pluck('id');

        // Get placement stats per player
        $placementStats = Placement::query()
            ->whereIn('round_id', $roundIds)
            ->select([
                'room_player_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_correct THEN 1 ELSE 0 END) as correct'),
            ])
            ->groupBy('room_player_id')
            ->get()
            ->keyBy('room_player_id');

        $standings = [];
        $rank = 0;
        $lastScore = null;
        $sameRankCount = 0;

        foreach ($players as $player) {
            if ($lastScore !== $player->score) {
                $rank += 1 + $sameRankCount;
                $sameRankCount = 0;
            } else {
                $sameRankCount++;
            }
            $lastScore = $player->score;

            $stats = $placementStats->get($player->id);
            $total = $stats?->total ?? 0;
            $correct = $stats?->correct ?? 0;

            $standings[] = [
                'rank' => $rank,
                'player_id' => $player->id,
                'display_name' => $player->display_name,
                'score' => $player->score,
                'is_me' => $currentPlayer?->id === $player->id,
                'correct_placements' => (int) $correct,
                'total_placements' => (int) $total,
                'accuracy_percentage' => $total > 0 ? (int) round(($correct / $total) * 100) : 0,
            ];
        }

        return $standings;
    }

    /**
     * Get the most accurate player (lowest average year delta).
     *
     * @return array{
     *     player_id: int,
     *     display_name: string,
     *     average_year_delta: float,
     *     correct_placements: int,
     *     total_placements: int
     * }|null
     */
    public function getMostAccuratePlayer(Room $room): ?array
    {
        $roundIds = $room->rounds()->pluck('id');

        if ($roundIds->isEmpty()) {
            return null;
        }

        $playerStats = Placement::query()
            ->whereIn('round_id', $roundIds)
            ->whereNotNull('year_delta')
            ->select([
                'room_player_id',
                DB::raw('AVG(year_delta) as avg_delta'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_correct THEN 1 ELSE 0 END) as correct'),
            ])
            ->groupBy('room_player_id')
            ->orderBy('avg_delta')
            ->first();

        if (! $playerStats) {
            return null;
        }

        $player = RoomPlayer::find($playerStats->room_player_id);

        if (! $player) {
            return null;
        }

        return [
            'player_id' => $player->id,
            'display_name' => $player->display_name,
            'average_year_delta' => round((float) $playerStats->avg_delta, 1),
            'correct_placements' => (int) $playerStats->correct,
            'total_placements' => (int) $playerStats->total,
        ];
    }

    /**
     * Get the biggest miss (highest year delta) with details.
     *
     * @return array{
     *     player_id: int,
     *     display_name: string,
     *     year_delta: int,
     *     round_number: int,
     *     track: array{title: string, artist: string, year: int}
     * }|null
     */
    public function getBiggestMiss(Room $room): ?array
    {
        $roundIds = $room->rounds()->pluck('id');

        if ($roundIds->isEmpty()) {
            return null;
        }

        $biggestMiss = Placement::query()
            ->whereIn('round_id', $roundIds)
            ->where('year_delta', '>', 0)
            ->orderByDesc('year_delta')
            ->with(['player', 'round.track'])
            ->first();

        if (! $biggestMiss) {
            return null;
        }

        $track = $biggestMiss->round->track;

        return [
            'player_id' => $biggestMiss->player->id,
            'display_name' => $biggestMiss->player->display_name,
            'year_delta' => $biggestMiss->year_delta,
            'round_number' => $biggestMiss->round->round_number,
            'track' => [
                'title' => $track->title,
                'artist' => $track->artist,
                'year' => (int) $track->release_date->format('Y'),
            ],
        ];
    }

    /**
     * Get AMEN statistics for the game.
     *
     * @return array{
     *     total_claims: int,
     *     by_player: array<int, array{
     *         player_id: int,
     *         display_name: string,
     *         claims: int,
     *         confirmed: int,
     *         rejected: int,
     *         ties: int,
     *         net_points: int
     *     }>
     * }
     */
    public function getAmenStats(Room $room): array
    {
        $rounds = $room->rounds()
            ->whereNotNull('amen_claimed_by_room_player_id')
            ->with('amenClaimant')
            ->get();

        if ($rounds->isEmpty()) {
            return [
                'total_claims' => 0,
                'by_player' => [],
            ];
        }

        $playerStats = [];

        foreach ($rounds as $round) {
            $playerId = $round->amen_claimed_by_room_player_id;
            $claimant = $round->amenClaimant;

            if (! $claimant) {
                continue;
            }

            if (! isset($playerStats[$playerId])) {
                $playerStats[$playerId] = [
                    'player_id' => $playerId,
                    'display_name' => $claimant->display_name,
                    'claims' => 0,
                    'confirmed' => 0,
                    'rejected' => 0,
                    'ties' => 0,
                    'net_points' => 0,
                ];
            }

            $playerStats[$playerId]['claims']++;

            $awardedPoints = $round->amen_awarded_points ?? 0;
            $playerStats[$playerId]['net_points'] += $awardedPoints;

            if ($awardedPoints > 0) {
                $playerStats[$playerId]['confirmed']++;
            } elseif ($awardedPoints < 0) {
                $playerStats[$playerId]['rejected']++;
            } else {
                $playerStats[$playerId]['ties']++;
            }
        }

        // Sort by net points descending
        usort($playerStats, static fn ($a, $b) => $b['net_points'] <=> $a['net_points']);

        return [
            'total_claims' => $rounds->count(),
            'by_player' => array_values($playerStats),
        ];
    }

    /**
     * Get overall game statistics.
     *
     * @return array{
     *     rounds_played: int,
     *     total_placements: int,
     *     overall_accuracy: int
     * }
     */
    public function getGameStats(Room $room): array
    {
        $roundIds = $room->rounds()->pluck('id');
        $roundsPlayed = $roundIds->count();

        if ($roundsPlayed === 0) {
            return [
                'rounds_played' => 0,
                'total_placements' => 0,
                'overall_accuracy' => 0,
            ];
        }

        $placementStats = Placement::query()
            ->whereIn('round_id', $roundIds)
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_correct THEN 1 ELSE 0 END) as correct'),
            ])
            ->first();

        $total = (int) ($placementStats->total ?? 0);
        $correct = (int) ($placementStats->correct ?? 0);

        return [
            'rounds_played' => $roundsPlayed,
            'total_placements' => $total,
            'overall_accuracy' => $total > 0 ? (int) round(($correct / $total) * 100) : 0,
        ];
    }
}
