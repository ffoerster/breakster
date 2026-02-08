<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AnalyticsStats;
use App\Filament\Widgets\GamesPlayedChart;
use App\Filament\Widgets\PopularPacksTable;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AnalyticsDashboard extends Page
{
    use HasFiltersForm;

    protected static ?string $title = 'Analytics';

    protected static string|\UnitEnum|null $navigationGroup = 'Analytics';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::ChartBar;

    protected static ?int $navigationSort = 10;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
        ]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('range')
                ->label('Range')
                ->options([
                    7 => 'Last 7 days',
                    30 => 'Last 30 days',
                    90 => 'Last 90 days',
                ])
                ->default(30)
                ->native(false)
                ->required(),
        ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AnalyticsStats::class,
            GamesPlayedChart::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            PopularPacksTable::class,
        ];
    }
}
