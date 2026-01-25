<?php

namespace App\Filament\Resources\Packs\Tables;

use App\Models\Pack;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PacksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('tracks_count')
                    ->counts('tracks')
                    ->label('Tracks')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
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
                Action::make('importFromTidal')
                    ->label('Import from TIDAL')
                    ->icon(Heroicon::ArrowDownTray)
                    ->modalHeading('Import from TIDAL')
                    ->modalDescription('Paste a TIDAL playlist/album URL or ID. (Import execution is wired in Epic F; for now this saves the source on the pack.)')
                    ->form([
                        TextInput::make('source')
                            ->label('Playlist/album URL or ID')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, Pack $record): void {
                        $record->update([
                            'tidal_source' => $data['source'],
                        ]);

                        Notification::make()
                            ->title('Saved TIDAL source')
                            ->body('Next: wire this to the queued import job (Epic F).')
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
