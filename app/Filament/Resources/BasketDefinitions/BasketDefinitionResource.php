<?php

namespace App\Filament\Resources\BasketDefinitions;

use App\Filament\Resources\BasketDefinitions\Pages\CreateBasketDefinition;
use App\Filament\Resources\BasketDefinitions\Pages\EditBasketDefinition;
use App\Filament\Resources\BasketDefinitions\Pages\ListBasketDefinitions;
use App\Filament\Resources\BasketDefinitions\RelationManagers\BasketItemsRelationManager;
use App\Filament\Resources\BasketDefinitions\Schemas\BasketDefinitionForm;
use App\Filament\Resources\BasketDefinitions\Tables\BasketDefinitionsTable;
use App\Models\BasketDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BasketDefinitionResource extends Resource
{
    protected static ?string $slug = 'baskets';

    protected static ?string $model = BasketDefinition::class;

    protected static ?string $modelLabel = 'Basket';

    protected static ?string $pluralModelLabel = 'Baskets';

    protected static ?string $navigationLabel = 'Baskets';

    protected static string|\UnitEnum|null $navigationGroup = 'Baskets';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof BasketDefinition) {
            return parent::getRecordTitle($record);
        }

        return $record->name_en ?? '—';
    }

    public static function form(Schema $schema): Schema
    {
        return BasketDefinitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BasketDefinitionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BasketItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBasketDefinitions::route('/'),
            'create' => CreateBasketDefinition::route('/create'),
            'edit' => EditBasketDefinition::route('/{record}/edit'),
        ];
    }
}
