<?php

namespace App\Filament\Resources\Tracks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TrackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Track Information')
                ->description('Basic track details and metadata')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')->required()->columnSpan(1),
                        TextInput::make('artist')->required()->columnSpan(1),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('label')
                            ->label('Record Label')
                            ->maxLength(255)
                            ->columnSpan(1),
                        DatePicker::make('release_date')->required()->columnSpan(1),
                        TextInput::make('difficulty')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->columnSpan(1),
                    ]),
                ])
                ->columns(1)
                ->collapsible(),

            Section::make('Provider Information')
                ->description('Music provider and track identification')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('pack_id')
                            ->relationship('pack', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),
                        Select::make('provider_id')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('provider_track_id')
                            ->required()
                            ->disabled(static fn(string $operation): bool => $operation === 'edit')
                            ->columnSpan(1),
                    ]),
                ])
                ->columns(1)
                ->collapsible(),

            Section::make('Artwork')
                ->description('Track artwork and visual assets')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('artwork_url')
                            ->label('Artwork URL')
                            ->url()
                            ->maxLength(2048)
                            ->live(onBlur: true)
                            ->columnSpan(1),
                        Placeholder::make('artwork_preview')
                            ->label('Preview')
                            ->content(static fn($record) => $record?->artwork_url
                                ? new HtmlString('
                                            <div class="flex items-center justify-center p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                                <img
                                                    src="'
                                .e($record->artwork_url)
                                .'"
                                                    alt="Artwork"
                                                    class="w-48 h-48 object-cover rounded-lg shadow-lg border-2 border-gray-200 dark:border-gray-700"
                                                />
                                            </div>
                                        ')
                                : new HtmlString('
                                            <div class="flex items-center justify-center p-8 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                                <span class="text-gray-400 dark:text-gray-600">No artwork available</span>
                                            </div>
                                        '))
                            ->columnSpan(1),
                    ]),
                ])
                ->columns(1)
                ->collapsible(),

            Section::make('External Links')
                ->description('Preview and streaming links')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('preview_url')
                            ->label('Preview URL')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpan(1),
                        TextInput::make('external_url')
                            ->label('External URL')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpan(1),
                    ]),
                ])
                ->columns(1)
                ->collapsible(),

            Section::make('Status')
                ->description('Track activation status')
                ->schema([
                    Toggle::make('is_active')
                        ->required()
                        ->default(true)
                        ->helperText('Enable or disable this track for gameplay'),
                ])
                ->columns(1)
                ->collapsible(),
        ]);
    }
}
