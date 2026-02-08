<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Analytics\GameAnalyticsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PopularPacksTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(GameAnalyticsService::class)->popularPacksQuery($this->pageFilters))
            ->columns([
                TextColumn::make('pack.name')
                    ->label('Pack')
                    ->default('Unknown'),
                TextColumn::make('games_played')
                    ->label('Games Played')
                    ->numeric(),
            ])
            ->defaultSort('games_played', 'desc')
            ->defaultKeySort(false)
            ->paginated(false);
    }
}
