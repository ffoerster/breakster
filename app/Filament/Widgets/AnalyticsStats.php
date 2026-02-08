<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Analytics\GameAnalyticsService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnalyticsStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $analytics = app(GameAnalyticsService::class);
        $range = $analytics->getRange($this->pageFilters);
        $retention = $analytics->retentionStats($this->pageFilters);

        return [
            Stat::make('Games Played', $analytics->gamesPlayedCount($this->pageFilters))
                ->description("Last {$range['days']} days")
                ->icon(Heroicon::ChartBar),
            Stat::make('Retention', $retention['retention_rate'].'%')
                ->description("Returning sessions: {$retention['returning_sessions']} / {$retention['total_sessions']}")
                ->icon(Heroicon::ArrowPath),
        ];
    }
}
