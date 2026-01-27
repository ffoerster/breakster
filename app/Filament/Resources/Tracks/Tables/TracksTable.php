<?php

namespace App\Filament\Resources\Tracks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TracksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('artwork_url')
                    ->label('Artwork')
                    ->square()
                    ->defaultImageUrl(url('/images/placeholder.png'))
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('artist')->searchable()->sortable(),
                TextColumn::make('label')
                    ->label('Label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('release_date')->date('Y')->sortable(),
                TextColumn::make('pack.name')->label('Pack')->sortable(),
                TextColumn::make('provider.name')->label('Provider')->sortable(),
                TextColumn::make('provider_track_id')->label('Provider ID')->searchable(),
                TextColumn::make('external_url')
                    ->label('External URL')
                    ->url(static fn($record): ?string => $record->external_url)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('difficulty')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('pack')
                    ->relationship('pack', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('provider')
                    ->relationship('provider', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('spotify')
                    ->label('Spotify')
                    ->queries(
                        true: static fn($query) => $query->whereHas('provider', static fn($q) => $q->where('key', 'spotify')),
                        false: static fn($query) => $query->whereHas('provider', static fn($q) => $q->where('key', '!=', 'spotify')),
                        blank: static fn($query) => $query,
                    ),
                TernaryFilter::make('tidal')
                    ->label('Tidal')
                    ->queries(
                        true: static fn($query) => $query->whereHas('provider', static fn($q) => $q->where('key', 'tidal')),
                        false: static fn($query) => $query->whereHas('provider', static fn($q) => $q->where('key', '!=', 'tidal')),
                        blank: static fn($query) => $query,
                    ),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
