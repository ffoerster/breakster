<?php

namespace App\Filament\Resources\Packs;

use App\Filament\Resources\Packs\Pages\CreatePack;
use App\Filament\Resources\Packs\Pages\EditPack;
use App\Filament\Resources\Packs\Pages\ListPacks;
use App\Filament\Resources\Packs\RelationManagers\TracksRelationManager;
use App\Filament\Resources\Packs\Schemas\PackForm;
use App\Filament\Resources\Packs\Tables\PacksTable;
use App\Models\Pack;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PackResource extends Resource
{
    protected static ?string $model = Pack::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PacksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TracksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPacks::route('/'),
            'create' => CreatePack::route('/create'),
            'edit' => EditPack::route('/{record}/edit'),
        ];
    }
}
