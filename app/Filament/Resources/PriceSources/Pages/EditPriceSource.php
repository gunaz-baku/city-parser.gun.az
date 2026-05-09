<?php

namespace App\Filament\Resources\PriceSources\Pages;

use App\Filament\Resources\PriceSources\PriceSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPriceSource extends EditRecord
{
    protected static string $resource = PriceSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
