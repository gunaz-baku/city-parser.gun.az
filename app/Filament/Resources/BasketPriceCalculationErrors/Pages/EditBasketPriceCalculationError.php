<?php

namespace App\Filament\Resources\BasketPriceCalculationErrors\Pages;

use App\Filament\Resources\BasketPriceCalculationErrors\BasketPriceCalculationErrorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBasketPriceCalculationError extends EditRecord
{
    protected static string $resource = BasketPriceCalculationErrorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
