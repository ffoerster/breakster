<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Models\GameSession;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GameAnalyticsService
{
    /**
     * @param  array<string, mixed> | null  $filters
     * @return array{start: Carbon, end: Carbon, days: int}
     */
    public function getRange(?array $filters = null): array
    {
        $days = $this->resolveRangeDays($filters);
        $end = now();
        $start = $end->copy()->subDays($days);

        return [
            'start' => $start,
            'end' => $end,
            'days' => $days,
        ];
    }

    /**
     * @param  array<string, mixed> | null  $filters
     */
    public function gamesPlayedCount(?array $filters = null): int
    {
        $range = $this->getRange($filters);

        return GameSession::query()
            ->whereBetween('finished_at', [$range['start'], $range['end']])
            ->whereNotNull('room_id')
            ->distinct('room_id')
            ->count('room_id');
    }

    /**
     * @param  array<string, mixed> | null  $filters
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function gamesPlayedTrend(?array $filters = null): array
    {
        $range = $this->getRange($filters);

        $raw = GameSession::query()
            ->whereBetween('finished_at', [$range['start'], $range['end']])
            ->whereNotNull('room_id')
            ->selectRaw('DATE(finished_at) as day, COUNT(DISTINCT room_id) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $period = CarbonPeriod::create(
            $range['start']->copy()->startOfDay(),
            $range['end']->copy()->startOfDay(),
        );

        $labels = [];
        $data = [];

        foreach ($period as $date) {
            $day = $date->toDateString();
            $labels[] = $day;
            $data[] = (int) ($raw->get($day)?->total ?? 0);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed> | null  $filters
     * @return array{total_sessions: int, returning_sessions: int, retention_rate: int}
     */
    public function retentionStats(?array $filters = null, int $windowDays = 7): array
    {
        $range = $this->getRange($filters);

        $sessions = GameSession::query()
            ->whereBetween('finished_at', [$range['start'], $range['end']])
            ->whereNotNull('session_id')
            ->orderBy('session_id')
            ->orderBy('finished_at')
            ->get(['session_id', 'finished_at'])
            ->groupBy('session_id');

        $totalSessions = $sessions->count();
        $returningSessions = 0;

        foreach ($sessions as $sessionEntries) {
            if ($sessionEntries->count() < 2) {
                continue;
            }

            $previous = null;
            foreach ($sessionEntries as $entry) {
                if ($previous && $previous->diffInDays($entry->finished_at) <= $windowDays) {
                    $returningSessions++;
                    break;
                }

                $previous = $entry->finished_at;
            }
        }

        $retentionRate = $totalSessions > 0
            ? (int) round(($returningSessions / $totalSessions) * 100)
            : 0;

        return [
            'total_sessions' => $totalSessions,
            'returning_sessions' => $returningSessions,
            'retention_rate' => $retentionRate,
        ];
    }

    /**
     * @param  array<string, mixed> | null  $filters
     */
    public function popularPacksQuery(?array $filters = null, int $limit = 5): Builder
    {
        $range = $this->getRange($filters);

        return GameSession::query()
            ->select('pack_id')
            ->selectRaw('COUNT(*) as games_played')
            ->selectRaw('MIN(id) as id')
            ->whereBetween('finished_at', [$range['start'], $range['end']])
            ->whereNotNull('pack_id')
            ->groupBy('pack_id')
            ->orderByDesc('games_played')
            ->limit($limit)
            ->with('pack');
    }

    /**
     * @param  array<string, mixed> | null  $filters
     */
    private function resolveRangeDays(?array $filters = null): int
    {
        $value = (int) ($filters['range'] ?? 30);

        return in_array($value, [7, 30, 90], true) ? $value : 30;
    }
}
