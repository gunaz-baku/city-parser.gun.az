<?php

namespace App\Filament\Resources\PricePositions;

use App\Filament\Resources\PricePositions\Pages\CreatePricePosition;
use App\Filament\Resources\PricePositions\Pages\EditPricePosition;
use App\Filament\Resources\PricePositions\Pages\ListPricePositions;
use App\Filament\Resources\PricePositions\Schemas\PricePositionForm;
use App\Filament\Resources\PricePositions\Tables\PricePositionsTable;
use App\Models\PricePosition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PricePositionResource extends Resource
{
    protected static ?string $slug = 'products';

    protected static ?string $model = PricePosition::class;

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?string $navigationLabel = 'Products';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof PricePosition) {
            return parent::getRecordTitle($record);
        }

        return $record->name_en ?? '—';
    }

    public static function form(Schema $schema): Schema
    {
        return PricePositionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PricePositionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPricePositions::route('/'),
            'create' => CreatePricePosition::route('/create'),
            'edit' => EditPricePosition::route('/{record}/edit'),
        ];
    }
}
