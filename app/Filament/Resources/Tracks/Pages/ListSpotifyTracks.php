<?php

namespace App\Filament\Resources\Tracks\Pages;

use App\Filament\Resources\Tracks\TrackResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSpotifyTracks extends ListRecords
{
    protected static string $resource = TrackResource::class;

    protected static ?string $title = 'Spotify Tracks';

    protected static ?string $navigationLabel = 'Spotify';

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()->whereHas('provider', static fn(Builder $query) => $query->where('key', 'spotify'));
    }
}
