<?php

namespace App\Filament\Resources\ParserRunErrors\Pages;

use App\Filament\Resources\ParserRunErrors\ParserRunErrorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditParserRunError extends EditRecord
{
    protected static string $resource = ParserRunErrorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
