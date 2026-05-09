<?php

namespace App\Filament\Resources\PriceUnits\Pages;

use App\Filament\Resources\PriceUnits\PriceUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPriceUnits extends ListRecords
{
    protected static string $resource = PriceUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
