<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Cities\Pages\ListCities;
use App\Filament\Resources\Cities\Schemas\CityForm;
use App\Filament\Resources\Cities\Tables\CitiesTable;
use App\Models\City;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CityResource extends Resource
{
    protected static ?string $slug = 'cities';

    protected static ?string $model = City::class;

    protected static ?string $modelLabel = 'City';

    protected static ?string $pluralModelLabel = 'Cities';

    protected static ?string $navigationLabel = 'Cities';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof City) {
            return parent::getRecordTitle($record);
        }

        return $record->name_en ?? (string) $record->id;
    }

    public static function form(Schema $schema): Schema
    {
        return CityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}
