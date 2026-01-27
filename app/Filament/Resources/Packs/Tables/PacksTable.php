<?php

namespace App\Filament\Resources\Packs\Tables;

use App\Domain\Packs\PackExportService;
use App\Domain\Providers\Tidal\TidalAdminTokenService;
use App\Domain\Providers\Tidal\TidalUrlParser;
use App\Jobs\ImportTidalTracksJob;
use App\Models\Pack;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use InvalidArgumentException;

class PacksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Cover')
                    ->circular()
                    ->defaultImageUrl(static fn(Pack $record): string => $record->image_url)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')->searchable(),
                TextColumn::make('tracks_count')
                    ->counts('tracks')
                    ->label('Total Tracks')
                    ->sortable(),
                TextColumn::make('spotify_tracks_count')
                    ->counts('spotifyTracks')
                    ->label('Spotify')
                    ->sortable(),
                TextColumn::make('tidal_tracks_count')
                    ->counts('tidalTracks')
                    ->label('Tidal')
                    ->sortable(),
                IconColumn::make('is_active')->boolean(),
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
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                Action::make('exportPack')
                    ->label('Export')
                    ->icon(Heroicon::ArrowUpTray)
                    ->action(static function (Pack $record): \Symfony\Component\HttpFoundation\StreamedResponse {
                        $service = app(PackExportService::class);
                        $json = $service->toJson($record);
                        $filename = $service->getFilename($record);

                        return response()->streamDownload(static fn() => print $json, $filename, [
                            'Content-Type' => 'application/json',
                        ]);
                    }),
                Action::make('importFromTidal')
                    ->label('Import from TIDAL')
                    ->icon(Heroicon::ArrowDownTray)
                    ->modalHeading('Import from TIDAL')
                    ->modalDescription(
                        'Paste a TIDAL track or playlist URL (or type track:<id> / playlist:<id>). This will enqueue a database-backed job to import/refresh tracks for this pack.',
                    )
                    ->form([
                        TextInput::make('source')
                            ->label('Track/playlist URL or ID')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(static function (array $data, Pack $record): void {
                        try {
                            $parsed = app(TidalUrlParser::class)->parse($data['source']);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Invalid TIDAL source')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($parsed['type'] === 'playlist' && !app(TidalAdminTokenService::class)->hasValidToken()) {
                            Notification::make()
                                ->title('Connect TIDAL to import playlists')
                                ->body(
                                    'Playlists require an admin connection. Open a pack and use the "Connect TIDAL" action first.',
                                )
                                ->warning()
                                ->send();

                            return;
                        }

                        ImportTidalTracksJob::dispatch($record->id, $data['source']);

                        Notification::make()
                            ->title('Import queued')
                            ->body('The import job has been queued. Run `ddev php artisan queue:work` to process it.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
