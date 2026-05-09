<?php

namespace App\Filament\Resources\PriceSnapshots;

use App\Filament\Resources\PriceSnapshots\Pages\EditPriceSnapshot;
use App\Filament\Resources\PriceSnapshots\Pages\ListPriceSnapshots;
use App\Filament\Resources\PriceSnapshots\Schemas\PriceSnapshotForm;
use App\Filament\Resources\PriceSnapshots\Tables\PriceSnapshotsTable;
use App\Models\PriceSnapshot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PriceSnapshotResource extends Resource
{
    protected static ?string $model = PriceSnapshot::class;

    protected static ?string $modelLabel = 'Price snapshot';

    protected static ?string $pluralModelLabel = 'Price snapshots';

    protected static ?string $navigationLabel = 'Price snapshots';

    protected static string|\UnitEnum|null $navigationGroup = 'Results';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PriceSnapshotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceSnapshotsTable::configure($table);
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
            'index' => ListPriceSnapshots::route('/'),
            'edit' => EditPriceSnapshot::route('/{record}/edit'),
        ];
    }
}
