<?php

namespace App\Filament\Resources\ParserRunErrors;

use App\Filament\Resources\ParserRunErrors\Pages\ListParserRunErrors;
use App\Filament\Resources\ParserRunErrors\Schemas\ParserRunErrorForm;
use App\Filament\Resources\ParserRunErrors\Tables\ParserRunErrorsTable;
use App\Models\ParserRunError;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ParserRunErrorResource extends Resource
{
    protected static ?string $slug = 'parser-errors';

    protected static ?string $model = ParserRunError::class;

    protected static ?string $modelLabel = 'Parser error';

    protected static ?string $pluralModelLabel = 'Parser errors';

    protected static ?string $navigationLabel = 'Parser errors';

    protected static string|\UnitEnum|null $navigationGroup = 'Parser';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

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
        return ParserRunErrorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParserRunErrorsTable::configure($table);
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
            'index' => ListParserRunErrors::route('/'),
        ];
    }
}
