<?php

namespace App\Filament\Resources\Packs\Pages;

use App\Domain\Providers\Tidal\TidalAdminTokenService;
use App\Domain\Providers\Tidal\TidalUrlParser;
use App\Filament\Resources\Packs\PackResource;
use App\Jobs\ImportTidalTracksJob;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;

class EditPack extends EditRecord
{
    protected static string $resource = PackResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (session()->pull('tidal.connected') === true) {
            Notification::make()
                ->title('TIDAL connected')
                ->success()
                ->send();
        }

        if (session()->pull('tidal.disconnected') === true) {
            Notification::make()
                ->title('TIDAL disconnected')
                ->success()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $isConnected = app(TidalAdminTokenService::class)->hasValidToken();

        return [
            Action::make('tidalStatus')
                ->label($isConnected ? 'TIDAL: Connected' : 'TIDAL: Not connected')
                ->disabled(),

            Action::make('connectTidal')
                ->label('Connect TIDAL')
                ->visible(! $isConnected)
                ->url(route('tidal.connect', ['redirect' => request()->fullUrl()])),

            Action::make('importTidal')
                ->label('Import from TIDAL')
                ->modalHeading('Import from TIDAL')
                ->modalDescription('Paste a TIDAL track or playlist URL (or type track:<id> / playlist:<id>). This will enqueue an import job for this pack.')
                ->form([
                    TextInput::make('source')
                        ->label('Track/playlist URL or ID')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
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

                    if ($parsed['type'] === 'playlist' && ! app(TidalAdminTokenService::class)->hasValidToken()) {
                        Notification::make()
                            ->title('Connect TIDAL to import playlists')
                            ->body('Playlists require an admin connection. Use the "Connect TIDAL" action first.')
                            ->warning()
                            ->send();

                        return;
                    }

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
