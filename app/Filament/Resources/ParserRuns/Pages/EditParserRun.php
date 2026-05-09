<?php

namespace App\Filament\Resources\ParserRuns\Pages;

use App\Filament\Resources\ParserRuns\ParserRunResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditParserRun extends EditRecord
{
    protected static string $resource = ParserRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
