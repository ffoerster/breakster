<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Analytics\GameAnalyticsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class GamesPlayedChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Games Played';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $trend = app(GameAnalyticsService::class)->gamesPlayedTrend($this->pageFilters);

        return [
            'datasets' => [
                [
                    'label' => 'Games',
                    'data' => $trend['data'],
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
