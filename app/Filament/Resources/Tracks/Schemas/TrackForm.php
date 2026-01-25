<?php

namespace App\Filament\Resources\Tracks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TrackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('pack_id')
                    ->relationship('pack', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('provider_id')
                    ->relationship('provider', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('provider_track_id')
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                TextInput::make('title')
                    ->required(),
                TextInput::make('artist')
                    ->required(),
                TextInput::make('label')
                    ->label('Record Label')
                    ->maxLength(255),
                DatePicker::make('release_date')
                    ->required(),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
                TextInput::make('difficulty')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10),
                TextInput::make('artwork_url')
                    ->label('Artwork URL')
                    ->url()
                    ->maxLength(2048),
                TextInput::make('preview_url')
                    ->label('Preview URL')
                    ->url()
                    ->maxLength(2048),
                TextInput::make('external_url')
                    ->label('External URL')
                    ->url()
                    ->maxLength(2048),
            ]);
    }
}
