<?php

namespace App\Filament\Resources\Packs\Pages;

use App\Domain\Packs\PackExportService;
use App\Domain\Packs\PackImportService;
use App\Domain\Providers\Spotify\SpotifyAdminTokenService;
use App\Domain\Providers\Spotify\SpotifyApiClient;
use App\Domain\Providers\Spotify\SpotifyClientCredentialsTokenService;
use App\Domain\Providers\Spotify\SpotifyUrlParser;
use App\Domain\Providers\Tidal\TidalAdminTokenService;
use App\Domain\Providers\Tidal\TidalApiClient;
use App\Domain\Providers\Tidal\TidalClientCredentialsTokenService;
use App\Domain\Providers\Tidal\TidalUrlParser;
use App\Filament\Resources\Packs\PackResource;
use App\Jobs\ImportSpotifyTracksJob;
use App\Jobs\ImportTidalTracksJob;
use App\Models\Provider;
use App\Models\Track;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
    public ?array $spotifyTrackPreview = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // TIDAL notifications
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

        // Spotify notifications
        if (session()->pull('spotify.connected') === true) {
            Notification::make()
                ->title('Spotify connected')
                ->success()
                ->send();
        }

        if (session()->pull('spotify.disconnected') === true) {
            Notification::make()
                ->title('Spotify disconnected')
                ->success()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $isTidalConnected = app(TidalAdminTokenService::class)->hasValidToken();
        $isSpotifyConnected = app(SpotifyAdminTokenService::class)->hasValidToken();

        return [
            // TIDAL dropdown
            ActionGroup::make([
                Action::make('connectTidal')
                    ->label('Connect account')
                    ->visible(! $isTidalConnected)
                    ->url(route('tidal.connect', ['redirect' => request()->fullUrl()])),

                Action::make('importTidal')
                    ->label('Import playlist or track')
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
                                ->body('Playlists require an admin connection. Use the "Connect account" action first.')
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
                    ->label('Import single track with preview')
                    ->modalHeading('Import single TIDAL track')
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
            ])
                ->label($isTidalConnected ? 'TIDAL' : 'TIDAL (not connected)')
                ->button()
                ->color($isTidalConnected ? 'success' : 'gray'),

            // Spotify dropdown
            ActionGroup::make([
                Action::make('connectSpotify')
                    ->label('Connect account')
                    ->visible(! $isSpotifyConnected)
                    ->url(route('spotify.connect', ['redirect' => request()->fullUrl()])),

                Action::make('importSpotify')
                    ->label('Import playlist or track')
                    ->modalHeading('Import from Spotify')
                    ->modalDescription('Paste a Spotify track or playlist URL (or spotify:track:<id> / spotify:playlist:<id>). This will enqueue an import job for this pack.')
                    ->form([
                        TextInput::make('source')
                            ->label('Track/playlist URL or URI')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $parsed = app(SpotifyUrlParser::class)->parse($data['source']);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Invalid Spotify source')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($parsed['type'] === 'playlist' && ! app(SpotifyAdminTokenService::class)->hasValidToken()) {
                            Notification::make()
                                ->title('Connect Spotify to import private playlists')
                                ->body('Private playlists require an admin connection. Use the "Connect account" action first.')
                                ->warning()
                                ->send();

                            return;
                        }

                        ImportSpotifyTracksJob::dispatch($this->record->id, $data['source']);

                        Notification::make()
                            ->title('Import queued')
                            ->success()
                            ->send();
                    }),

                Action::make('importSingleSpotifyTrack')
                    ->label('Import single track with preview')
                    ->modalHeading('Import single Spotify track')
                    ->modalDescription('Paste a Spotify track URL, fetch metadata, then import it into this pack.')
                    ->modalWidth('xl')
                    ->mountUsing(function (): void {
                        $this->spotifyTrackPreview = null;
                    })
                    ->modalSubmitActionLabel(fn (): string => $this->spotifyTrackPreview === null ? 'Fetch metadata' : 'Import')
                    ->form([
                        TextInput::make('source_url')
                            ->label('Track URL')
                            ->required()
                            ->maxLength(255),

                        Placeholder::make('spotify_preview_title')
                            ->label('Title')
                            ->content(fn (): string => (string) ($this->spotifyTrackPreview['title'] ?? ''))
                            ->visible(fn (): bool => $this->spotifyTrackPreview !== null),
                        Placeholder::make('spotify_preview_artist')
                            ->label('Artist')
                            ->content(fn (): string => (string) ($this->spotifyTrackPreview['artist'] ?? ''))
                            ->visible(fn (): bool => $this->spotifyTrackPreview !== null),
                        Placeholder::make('spotify_preview_label')
                            ->label('Label')
                            ->content(fn (): string => (string) ($this->spotifyTrackPreview['label'] ?? ''))
                            ->visible(fn (): bool => $this->spotifyTrackPreview !== null),
                        Placeholder::make('spotify_preview_release_date')
                            ->label('Release date')
                            ->content(fn (): string => (string) ($this->spotifyTrackPreview['release_date'] ?? ''))
                            ->visible(fn (): bool => $this->spotifyTrackPreview !== null),
                        Placeholder::make('spotify_preview_artwork_url')
                            ->label('Artwork URL')
                            ->content(fn (): string => (string) ($this->spotifyTrackPreview['artwork_url'] ?? ''))
                            ->visible(fn (): bool => $this->spotifyTrackPreview !== null),
                        Placeholder::make('spotify_preview_external_url')
                            ->label('External URL')
                            ->content(fn (): string => (string) ($this->spotifyTrackPreview['external_url'] ?? ''))
                            ->visible(fn (): bool => $this->spotifyTrackPreview !== null),
                    ])
                    ->action(function (array $data, Action $action): void {
                        try {
                            $parsed = app(SpotifyUrlParser::class)->parse($data['source_url']);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Invalid Spotify URL')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($parsed['type'] !== 'track') {
                            Notification::make()
                                ->title('Only tracks are supported')
                                ->body('Paste a Spotify track URL like https://open.spotify.com/track/<id>.')
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($this->spotifyTrackPreview === null) {
                            try {
                                $token = app(SpotifyClientCredentialsTokenService::class)->getAccessToken();
                                $track = app(SpotifyApiClient::class)->getTrack((string) $parsed['id'], $token);
                            } catch (RuntimeException $e) {
                                Notification::make()
                                    ->title('Unable to fetch metadata')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $this->spotifyTrackPreview = $track;

                            Notification::make()
                                ->title('Metadata fetched')
                                ->success()
                                ->send();

                            $action->halt();

                            return;
                        }

                        $provider = Provider::query()->firstOrCreate(
                            ['key' => 'spotify'],
                            ['name' => 'Spotify', 'is_active' => true],
                        );

                        $providerTrackId = (string) ($this->spotifyTrackPreview['provider_track_id'] ?? '');
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
                                'title' => (string) $this->spotifyTrackPreview['title'],
                                'artist' => (string) $this->spotifyTrackPreview['artist'],
                                'label' => $this->spotifyTrackPreview['label'],
                                'release_date' => (string) $this->spotifyTrackPreview['release_date'],
                                'artwork_url' => $this->spotifyTrackPreview['artwork_url'],
                                'preview_url' => $this->spotifyTrackPreview['preview_url'],
                                'external_url' => (string) $this->spotifyTrackPreview['external_url'],
                                'is_active' => true,
                            ],
                        );

                        $this->spotifyTrackPreview = null;

                        Notification::make()
                            ->title('Track imported')
                            ->success()
                            ->send();
                    }),
            ])
                ->label($isSpotifyConnected ? 'Spotify' : 'Spotify (not connected)')
                ->button()
                ->color($isSpotifyConnected ? 'success' : 'gray'),

            // Pack management dropdown
            ActionGroup::make([
                Action::make('exportPack')
                    ->label('Export to JSON')
                    ->icon(Heroicon::ArrowUpTray)
                    ->action(function (): \Symfony\Component\HttpFoundation\StreamedResponse {
                        $service = app(PackExportService::class);
                        $json = $service->toJson($this->record);
                        $filename = $service->getFilename($this->record);

                        return response()->streamDownload(
                            fn () => print ($json),
                            $filename,
                            ['Content-Type' => 'application/json']
                        );
                    }),

                Action::make('importTracksFromJson')
                    ->label('Import tracks from JSON')
                    ->icon(Heroicon::ArrowDownTray)
                    ->modalHeading('Import Tracks from JSON')
                    ->modalDescription('Upload a pack export JSON file to import its tracks into this pack.')
                    ->form([
                        FileUpload::make('json_file')
                            ->label('JSON File')
                            ->required()
                            ->acceptedFileTypes(['application/json', 'text/plain'])
                            ->maxSize(5120)
                            ->disk('local')
                            ->directory('temp-imports')
                            ->visibility('private'),
                        Checkbox::make('update_existing')
                            ->label('Update existing tracks if they already exist')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        $service = app(PackImportService::class);

                        try {
                            $filePath = $data['json_file'];
                            $contents = Storage::disk('local')->get($filePath);
                            $parsed = $service->parseJsonFile($contents);
                            $result = $service->importTracksIntoPack($this->record, $parsed, $data['update_existing']);

                            Storage::disk('local')->delete($filePath);

                            Notification::make()
                                ->title('Tracks imported successfully')
                                ->body(sprintf(
                                    '%d new tracks, %d updated, %d skipped.',
                                    $result['imported'],
                                    $result['updated'],
                                    $result['skipped']
                                ))
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Invalid import file')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Invalid JSON file')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
                ->label('Pack')
                ->button()
                ->color('gray'),

            DeleteAction::make(),
        ];
    }
}
