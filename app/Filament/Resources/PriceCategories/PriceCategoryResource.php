<?php

namespace App\Filament\Resources\PriceCategories;

use App\Filament\Resources\PriceCategories\Pages\CreatePriceCategory;
use App\Filament\Resources\PriceCategories\Pages\EditPriceCategory;
use App\Filament\Resources\PriceCategories\Pages\ListPriceCategories;
use App\Filament\Resources\PriceCategories\Schemas\PriceCategoryForm;
use App\Filament\Resources\PriceCategories\Tables\PriceCategoriesTable;
use App\Models\PriceCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PriceCategoryResource extends Resource
{
    protected static ?string $slug = 'categories';

    protected static ?string $model = PriceCategory::class;

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Categories';

    protected static ?string $navigationLabel = 'Categories';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof PriceCategory) {
            return parent::getRecordTitle($record);
        }

        return $record->name_en ?? '—';
    }

    public static function form(Schema $schema): Schema
    {
        return PriceCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceCategoriesTable::configure($table);
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
            'index' => ListPriceCategories::route('/'),
            'create' => CreatePriceCategory::route('/create'),
            'edit' => EditPriceCategory::route('/{record}/edit'),
        ];
    }
}
