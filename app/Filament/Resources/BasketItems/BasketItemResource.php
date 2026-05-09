<?php

namespace App\Filament\Resources\BasketItems;

use App\Filament\Resources\BasketItems\Pages\CreateBasketItem;
use App\Filament\Resources\BasketItems\Pages\EditBasketItem;
use App\Filament\Resources\BasketItems\Pages\ListBasketItems;
use App\Filament\Resources\BasketItems\Schemas\BasketItemForm;
use App\Filament\Resources\BasketItems\Tables\BasketItemsTable;
use App\Models\BasketItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BasketItemResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = BasketItem::class;

    protected static ?string $modelLabel = 'Səbət elementi';

    protected static ?string $pluralModelLabel = 'Səbət elementləri';

    protected static string|\UnitEnum|null $navigationGroup = 'Snapşot və səbət';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return BasketItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BasketItemsTable::configure($table);
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
            'index' => ListBasketItems::route('/'),
            'create' => CreateBasketItem::route('/create'),
            'edit' => EditBasketItem::route('/{record}/edit'),
        ];
    }
}
