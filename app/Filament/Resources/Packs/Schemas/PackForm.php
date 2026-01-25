<?php

namespace App\Filament\Resources\Packs\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            Textarea::make('description')->columnSpanFull(),
            FileUpload::make('image_path')
                ->label('Cover Image')
                ->image()
                ->disk('public')
                ->directory('packs')
                ->visibility('public')
                ->columnSpanFull(),
            Toggle::make('is_active')->required()->default(true),
        ]);
    }
}
