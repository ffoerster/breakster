<?php

namespace App\Filament\Resources\Tracks\Pages;

use App\Domain\Providers\Tidal\TidalApiClient;
use App\Domain\Providers\Tidal\TidalClientCredentialsTokenService;
use App\Domain\Providers\Tidal\TidalUrlParser;
use App\Filament\Resources\Tracks\TrackResource;
use App\Models\Pack;
use App\Models\Provider;
use App\Models\Track;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use InvalidArgumentException;
use RuntimeException;

class ListTracks extends ListRecords
{
    protected static string $resource = TrackResource::class;

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

    public ?int $tidalTrackPackId = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importSingleTidalTrack')
                ->label('Import single track URL')
                ->modalHeading('Import single track URL')
                ->modalDescription('Pick a pack, paste a TIDAL track URL, fetch metadata, then import it.')
                ->modalWidth('xl')
                ->mountUsing(function (): void {
                    $this->tidalTrackPreview = null;
                    $this->tidalTrackPackId = null;
                })
                ->fillForm([
                    'pack_id' => null,
                    'source_url' => null,
                ])
                ->modalSubmitActionLabel(fn(): string => $this->tidalTrackPreview === null
                    ? 'Fetch metadata'
                    : 'Import')
                ->form([
                    Select::make('pack_id')
                        ->label('Pack')
                        ->required()
                        ->live()
                        ->preload()
                        ->searchable()
                        ->options(
                            static fn(): array => Pack::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all(),
                        ),
                    TextInput::make('source_url')
                        ->label('Track URL')
                        ->required()
                        ->maxLength(255),

                    Placeholder::make('preview_title')
                        ->label('Title')
                        ->content(fn(): string => (string) ( $this->tidalTrackPreview['title'] ?? '' ))
                        ->visible(fn(): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_artist')
                        ->label('Artist')
                        ->content(fn(): string => (string) ( $this->tidalTrackPreview['artist'] ?? '' ))
                        ->visible(fn(): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_label')
                        ->label('Label')
                        ->content(fn(): string => (string) ( $this->tidalTrackPreview['label'] ?? '' ))
                        ->visible(fn(): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_release_date')
                        ->label('Release date')
                        ->content(fn(): string => (string) ( $this->tidalTrackPreview['release_date'] ?? '' ))
                        ->visible(fn(): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_artwork_url')
                        ->label('Artwork URL')
                        ->content(fn(): string => (string) ( $this->tidalTrackPreview['artwork_url'] ?? '' ))
                        ->visible(fn(): bool => $this->tidalTrackPreview !== null),
                    Placeholder::make('preview_external_url')
                        ->label('External URL')
                        ->content(fn(): string => (string) ( $this->tidalTrackPreview['external_url'] ?? '' ))
                        ->visible(fn(): bool => $this->tidalTrackPreview !== null),
                ])
                ->action(function (array $data, Action $action): void {
                    if ($this->tidalTrackPreview === null) {
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
                        $this->tidalTrackPackId = (int) $data['pack_id'];

                        Notification::make()
                            ->title('Metadata fetched')
                            ->success()
                            ->send();

                        $action->halt();

                        return;
                    }

                    $packId = $this->tidalTrackPackId ?? (int) $data['pack_id'];

                    $provider = Provider::query()->firstOrCreate(['key' => 'tidal'], [
                        'name' => 'TIDAL',
                        'is_active' => true,
                    ]);

                    $providerTrackId = (string) ( $this->tidalTrackPreview['provider_track_id'] ?? '' );
                    if ($providerTrackId === '') {
                        Notification::make()
                            ->title('Unable to import')
                            ->body('Missing provider track id.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Track::query()->updateOrCreate([
                        'pack_id' => $packId,
                        'provider_id' => $provider->id,
                        'provider_track_id' => $providerTrackId,
                    ], [
                        'title' => (string) $this->tidalTrackPreview['title'],
                        'artist' => (string) $this->tidalTrackPreview['artist'],
                        'label' => $this->tidalTrackPreview['label'],
                        'release_date' => (string) $this->tidalTrackPreview['release_date'],
                        'artwork_url' => $this->tidalTrackPreview['artwork_url'],
                        'preview_url' => $this->tidalTrackPreview['preview_url'],
                        'external_url' => (string) $this->tidalTrackPreview['external_url'],
                        'is_active' => true,
                    ]);

                    $this->tidalTrackPreview = null;
                    $this->tidalTrackPackId = null;

                    Notification::make()
                        ->title('Track imported')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
