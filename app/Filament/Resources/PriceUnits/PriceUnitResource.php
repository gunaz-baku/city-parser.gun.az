<?php

namespace App\Filament\Resources\PriceUnits;

use App\Filament\Resources\PriceUnits\Pages\CreatePriceUnit;
use App\Filament\Resources\PriceUnits\Pages\EditPriceUnit;
use App\Filament\Resources\PriceUnits\Pages\ListPriceUnits;
use App\Filament\Resources\PriceUnits\Schemas\PriceUnitForm;
use App\Filament\Resources\PriceUnits\Tables\PriceUnitsTable;
use App\Models\PriceUnit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PriceUnitResource extends Resource
{
    protected static ?string $slug = 'units';

    protected static ?string $model = PriceUnit::class;

    protected static ?string $modelLabel = 'Unit';

    protected static ?string $pluralModelLabel = 'Units';

    protected static ?string $navigationLabel = 'Units';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof PriceUnit) {
            return parent::getRecordTitle($record);
        }

        return $record->displayLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return PriceUnitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceUnitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceUnits::route('/'),
            'create' => CreatePriceUnit::route('/create'),
            'edit' => EditPriceUnit::route('/{record}/edit'),
        ];
    }
}
