<?php

declare(strict_types=1);

namespace App\Filament\Resources\Packs\RelationManagers;

use App\Filament\Resources\Tracks\TrackResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TracksRelationManager extends RelationManager
{
    protected static string $relationship = 'tracks';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('artist')
                    ->searchable(),
                TextColumn::make('release_date')
                    ->date('Y')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Edit')
                    ->url(fn ($record): string => TrackResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
