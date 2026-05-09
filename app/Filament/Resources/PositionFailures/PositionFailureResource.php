<?php

namespace App\Filament\Resources\PositionFailures;

use App\Filament\Resources\PositionFailures\Pages\ListPositionFailures;
use App\Filament\Resources\PositionFailures\Schemas\PositionFailureForm;
use App\Filament\Resources\PositionFailures\Tables\PositionFailuresTable;
use App\Models\PositionFailure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PositionFailureResource extends Resource
{
    protected static ?string $slug = 'product-failures';

    protected static ?string $model = PositionFailure::class;

    protected static ?string $modelLabel = 'Product failure';

    protected static ?string $pluralModelLabel = 'Product failures';

    protected static ?string $navigationLabel = 'Product failures';

    protected static string|\UnitEnum|null $navigationGroup = 'Parser';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedXCircle;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PositionFailureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PositionFailuresTable::configure($table);
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
            'index' => ListPositionFailures::route('/'),
        ];
    }
}
