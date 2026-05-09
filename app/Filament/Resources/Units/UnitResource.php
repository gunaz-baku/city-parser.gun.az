<?php

namespace App\Filament\Resources\Units;

use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\EditUnit;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Filament\Resources\Units\Schemas\UnitForm;
use App\Filament\Resources\Units\Tables\UnitsTable;
use App\Models\Unit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UnitResource extends Resource
{
    protected static ?string $slug = 'units';

    protected static ?string $model = Unit::class;

    protected static ?string $modelLabel = 'Unit';

    protected static ?string $pluralModelLabel = 'Units';

    protected static ?string $navigationLabel = 'Units';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof Unit) {
            return parent::getRecordTitle($record);
        }

        return $record->displayLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return UnitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnits::route('/'),
            'create' => CreateUnit::route('/create'),
            'edit' => EditUnit::route('/{record}/edit'),
        ];
    }
}
