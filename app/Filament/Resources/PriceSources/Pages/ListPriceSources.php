<?php

namespace App\Filament\Resources\PriceSources\Pages;

use App\Filament\Resources\PriceSources\PriceSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPriceSources extends ListRecords
{
    protected static string $resource = PriceSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
