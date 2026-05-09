<?php

namespace App\Filament\Resources\ParserRuns\Pages;

use App\Filament\Resources\ParserRuns\ParserRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParserRuns extends ListRecords
{
    protected static string $resource = ParserRunResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::canCreate()
            ? [CreateAction::make()]
            : [];
    }
}
