<?php

namespace App\Filament\Resources\BasketPriceCalculationErrors\Pages;

use App\Filament\Resources\BasketPriceCalculationErrors\BasketPriceCalculationErrorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBasketPriceCalculationErrors extends ListRecords
{
    protected static string $resource = BasketPriceCalculationErrorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
