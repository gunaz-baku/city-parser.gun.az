<?php

namespace App\Filament\Resources\ParserRunErrors\Pages;

use App\Filament\Resources\ParserRunErrors\ParserRunErrorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParserRunErrors extends ListRecords
{
    protected static string $resource = ParserRunErrorResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::canCreate()
            ? [CreateAction::make()]
            : [];
    }
}
