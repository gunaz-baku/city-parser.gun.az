<?php

namespace App\Filament\Resources\ParserRuns;

use App\Filament\Resources\ParserRuns\Pages\EditParserRun;
use App\Filament\Resources\ParserRuns\Pages\ListParserRuns;
use App\Filament\Resources\ParserRuns\Schemas\ParserRunForm;
use App\Filament\Resources\ParserRuns\Tables\ParserRunsTable;
use App\Models\ParserRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ParserRunResource extends Resource
{
    protected static ?string $model = ParserRun::class;

    protected static ?string $modelLabel = 'Parser run';

    protected static ?string $pluralModelLabel = 'Parser runs';

    protected static ?string $navigationLabel = 'Parser runs';

    protected static string|\UnitEnum|null $navigationGroup = 'Parser';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlay;

    protected static bool $hasTitleCaseModelLabel = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ParserRunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParserRunsTable::configure($table);
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
            'index' => ListParserRuns::route('/'),
            'edit' => EditParserRun::route('/{record}/edit'),
        ];
    }
}
