<?php

namespace App\Filament\Resources\Packs\Pages;

use App\Domain\Packs\PackImportService;
use App\Filament\Resources\Packs\PackResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ListPacks extends ListRecords
{
    protected static string $resource = PackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importPack')
                ->label('Import Pack')
                ->icon(Heroicon::ArrowDownTray)
                ->modalHeading('Import Pack from JSON')
                ->modalDescription(
                    'Upload a previously exported pack JSON file to create a new pack with all its tracks.',
                )
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
                        $result = $service->import($parsed, $data['update_existing']);

                        Storage::disk('local')->delete($filePath);

                        Notification::make()
                            ->title('Pack imported successfully')
                            ->body(sprintf(
                                'Created pack "%s" with %d new tracks, %d updated, %d skipped.',
                                $result['pack']->name,
                                $result['imported'],
                                $result['updated'],
                                $result['skipped'],
                            ))
                            ->success()
                            ->send();

                        $this->redirect(PackResource::getUrl('edit', ['record' => $result['pack']]));
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
            CreateAction::make(),
        ];
    }
}
