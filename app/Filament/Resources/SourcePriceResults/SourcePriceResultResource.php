<?php

namespace App\Filament\Resources\SourcePriceResults;

use App\Filament\Resources\SourcePriceResults\Pages\CreateSourcePriceResult;
use App\Filament\Resources\SourcePriceResults\Pages\EditSourcePriceResult;
use App\Filament\Resources\SourcePriceResults\Pages\ListSourcePriceResults;
use App\Filament\Resources\SourcePriceResults\Schemas\SourcePriceResultForm;
use App\Filament\Resources\SourcePriceResults\Tables\SourcePriceResultsTable;
use App\Models\SourcePriceResult;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SourcePriceResultResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = SourcePriceResult::class;

    protected static ?string $modelLabel = 'Mənbə qiymət nəticəsi';

    protected static ?string $pluralModelLabel = 'Mənbə qiymət nəticələri';

    protected static string|\UnitEnum|null $navigationGroup = 'Parser';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return SourcePriceResultForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SourcePriceResultsTable::configure($table);
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
            'index' => ListSourcePriceResults::route('/'),
            'create' => CreateSourcePriceResult::route('/create'),
            'edit' => EditSourcePriceResult::route('/{record}/edit'),
        ];
    }
}
