<?php

namespace App\Filament\Resources\PriceUnits\Pages;

use App\Filament\Resources\PriceUnits\PriceUnitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPriceUnit extends EditRecord
{
    protected static string $resource = PriceUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
