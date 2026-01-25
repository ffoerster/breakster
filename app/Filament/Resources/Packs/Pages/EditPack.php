<?php

namespace App\Filament\Resources\Packs\Pages;

use App\Filament\Resources\Packs\PackResource;
use App\Jobs\ImportTidalTracksJob;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPack extends EditRecord
{
    protected static string $resource = PackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importTidal')
                ->label('Import from TIDAL')
                ->modalHeading('Import from TIDAL')
                ->modalDescription('Paste a TIDAL playlist/album URL or ID. This will enqueue an import job for this pack.')
                ->form([
                    TextInput::make('source')
                        ->label('Playlist/album URL or ID')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'tidal_source' => $data['source'],
                    ]);

                    ImportTidalTracksJob::dispatch($this->record->id, $data['source']);

                    Notification::make()
                        ->title('Import queued')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
