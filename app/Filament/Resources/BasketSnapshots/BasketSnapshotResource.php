<?php

namespace App\Filament\Resources\BasketSnapshots;

use App\Filament\Resources\BasketSnapshots\Pages\EditBasketSnapshot;
use App\Filament\Resources\BasketSnapshots\Pages\ListBasketSnapshots;
use App\Filament\Resources\BasketSnapshots\Schemas\BasketSnapshotForm;
use App\Filament\Resources\BasketSnapshots\Tables\BasketSnapshotsTable;
use App\Models\BasketSnapshot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BasketSnapshotResource extends Resource
{
    protected static ?string $slug = 'basket-prices';

    protected static ?string $model = BasketSnapshot::class;

    protected static ?string $modelLabel = 'Basket price';

    protected static ?string $pluralModelLabel = 'Basket prices';

    protected static ?string $navigationLabel = 'Basket prices';

    protected static string|\UnitEnum|null $navigationGroup = 'Baskets';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return BasketSnapshotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BasketSnapshotsTable::configure($table);
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
            'index' => ListBasketSnapshots::route('/'),
            'edit' => EditBasketSnapshot::route('/{record}/edit'),
        ];
    }
}
