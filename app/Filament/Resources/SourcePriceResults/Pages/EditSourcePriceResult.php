<?php

namespace App\Filament\Resources\SourcePriceResults\Pages;

use App\Filament\Resources\SourcePriceResults\SourcePriceResultResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSourcePriceResult extends EditRecord
{
    protected static string $resource = SourcePriceResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
