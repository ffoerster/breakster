<?php

namespace App\Filament\Resources\Packs\Pages;

use App\Domain\Providers\Tidal\TidalAdminTokenService;
use App\Domain\Providers\Tidal\TidalApiClient;
use App\Domain\Providers\Tidal\TidalClientCredentialsTokenService;
use App\Domain\Providers\Tidal\TidalUrlParser;
use App\Filament\Resources\Packs\PackResource;
use App\Jobs\ImportTidalTracksJob;
use App\Models\Provider;
use App\Models\Track;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;
use RuntimeException;

class EditPack extends EditRecord
{
    protected static string $resource = PackResource::class;

    /**
     * @var array{
     *     provider_track_id: string,
     *     title: string,
     *     artist: string,
     *     label: string|null,
     *     release_date: string,
     *     artwork_url: string|null,
     *     preview_url: string|null,
     *     external_url: string,
     * }|null
     */
    public ?array $tidalTrackPreview = null;

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

            Action::make('importSingleTidalTrack')
                ->label('Import single track URL')
                ->modalHeading('Import single track URL')
                ->modalDescription('Paste a TIDAL track URL, fetch metadata, then import it into this pack.')
                ->modalWidth('xl')
                ->mountUsing(function (): void {
                    $this->tidalTrackPreview = null;
                })
                ->modalSubmitActionLabel(fn (): string => $this->tidalTrackPreview === null ? 'Fetch metadata' : 'Import')
                ->form([
                    TextInput::make('source_url')
                        ->label('Track URL')
                        ->required()
                        ->maxLength(255),

                    Placeholder::make('preview_title')
                        ->label('Title')
                        ->content(fn (): string => (string) ($this->tidalTrackPreview['title'] ?? ''))
                        ->visible(fn (): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_artist')
                        ->label('Artist')
                        ->content(fn (): string => (string) ($this->tidalTrackPreview['artist'] ?? ''))
                        ->visible(fn (): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_label')
                        ->label('Label')
                        ->content(fn (): string => (string) ($this->tidalTrackPreview['label'] ?? ''))
                        ->visible(fn (): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_release_date')
                        ->label('Release date')
                        ->content(fn (): string => (string) ($this->tidalTrackPreview['release_date'] ?? ''))
                        ->visible(fn (): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_artwork_url')
                        ->label('Artwork URL')
                        ->content(fn (): string => (string) ($this->tidalTrackPreview['artwork_url'] ?? ''))
                        ->visible(fn (): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_external_url')
                        ->label('External URL')
                        ->content(fn (): string => (string) ($this->tidalTrackPreview['external_url'] ?? ''))
                        ->visible(fn (): bool => $this->tidalTrackPreview !== null),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        $parsed = app(TidalUrlParser::class)->parse($data['source_url']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Invalid TIDAL URL')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($parsed['type'] !== 'track') {
                        Notification::make()
                            ->title('Only tracks are supported')
                            ->body('Paste a TIDAL track URL like https://tidal.com/track/<id>.')
                            ->warning()
                            ->send();

                        return;
                    }

                    if ($this->tidalTrackPreview === null) {
                        try {
                            $token = app(TidalClientCredentialsTokenService::class)->getAccessToken();
                            $track = app(TidalApiClient::class)->getTrack((string) $parsed['id'], $token);
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->title('Unable to fetch metadata')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $this->tidalTrackPreview = $track;

                        Notification::make()
                            ->title('Metadata fetched')
                            ->success()
                            ->send();

                        $action->halt();

                        return;
                    }

                    $provider = Provider::query()->firstOrCreate(
                        ['key' => 'tidal'],
                        ['name' => 'TIDAL', 'is_active' => true],
                    );

                    $providerTrackId = (string) ($this->tidalTrackPreview['provider_track_id'] ?? '');
                    if ($providerTrackId === '') {
                        Notification::make()
                            ->title('Unable to import')
                            ->body('Missing provider track id.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Track::query()->updateOrCreate(
                        [
                            'pack_id' => $this->record->id,
                            'provider_id' => $provider->id,
                            'provider_track_id' => $providerTrackId,
                        ],
                        [
                            'title' => (string) $this->tidalTrackPreview['title'],
                            'artist' => (string) $this->tidalTrackPreview['artist'],
                            'label' => $this->tidalTrackPreview['label'],
                            'release_date' => (string) $this->tidalTrackPreview['release_date'],
                            'artwork_url' => $this->tidalTrackPreview['artwork_url'],
                            'preview_url' => $this->tidalTrackPreview['preview_url'],
                            'external_url' => (string) $this->tidalTrackPreview['external_url'],
                            'is_active' => true,
                        ],
                    );

                    $this->tidalTrackPreview = null;

                    Notification::make()
                        ->title('Track imported')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
