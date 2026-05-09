<?php

namespace App\Filament\Resources\PriceSources;

use App\Filament\Resources\PriceSources\Pages\CreatePriceSource;
use App\Filament\Resources\PriceSources\Pages\EditPriceSource;
use App\Filament\Resources\PriceSources\Pages\ListPriceSources;
use App\Filament\Resources\PriceSources\Schemas\PriceSourceForm;
use App\Filament\Resources\PriceSources\Tables\PriceSourcesTable;
use App\Models\PriceSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PriceSourceResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = PriceSource::class;

    protected static ?string $modelLabel = 'Qiymət mənbəyi';

    protected static ?string $pluralModelLabel = 'Qiymət mənbələri';

    protected static string|\UnitEnum|null $navigationGroup = 'Kataloq';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof PriceSource) {
            return parent::getRecordTitle($record);
        }

        $n = is_array($record->links_json) ? count($record->links_json) : 0;

        return '#'.$record->id.' — '.(string) $record->source_type.' ('.$n.' link)';
    }

    public static function form(Schema $schema): Schema
    {
        return PriceSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceSourcesTable::configure($table);
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
            'index' => ListPriceSources::route('/'),
            'create' => CreatePriceSource::route('/create'),
            'edit' => EditPriceSource::route('/{record}/edit'),
        ];
    }
}
