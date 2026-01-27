<?php

namespace App\Filament\Resources\Tracks\Pages;

use App\Filament\Resources\Tracks\TrackResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTidalTracks extends ListRecords
{
    protected static string $resource = TrackResource::class;

    protected static ?string $title = 'Tidal Tracks';

    protected static ?string $navigationLabel = 'Tidal';

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->whereHas('provider', static fn(Builder $query) => $query->where('key', 'tidal'));
    }
}
