<?php

namespace App\Filament\Resources\BasketPriceCalculationErrors;

use App\Filament\Resources\BasketPriceCalculationErrors\Pages\CreateBasketPriceCalculationError;
use App\Filament\Resources\BasketPriceCalculationErrors\Pages\EditBasketPriceCalculationError;
use App\Filament\Resources\BasketPriceCalculationErrors\Pages\ListBasketPriceCalculationErrors;
use App\Filament\Resources\BasketPriceCalculationErrors\Schemas\BasketPriceCalculationErrorForm;
use App\Filament\Resources\BasketPriceCalculationErrors\Tables\BasketPriceCalculationErrorsTable;
use App\Models\BasketPriceCalculationError;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BasketPriceCalculationErrorResource extends Resource
{
    protected static ?string $model = BasketPriceCalculationError::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Basket Calculation Errors';

    protected static string|\UnitEnum|null $navigationGroup = 'Baskets';


    public static function form(Schema $schema): Schema
    {
        return BasketPriceCalculationErrorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BasketPriceCalculationErrorsTable::configure($table);
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
            'index' => ListBasketPriceCalculationErrors::route('/'),
            'create' => CreateBasketPriceCalculationError::route('/create'),
            'edit' => EditBasketPriceCalculationError::route('/{record}/edit'),
        ];
    }
}
