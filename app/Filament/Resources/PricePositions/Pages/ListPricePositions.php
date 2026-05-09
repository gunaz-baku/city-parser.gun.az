<?php

namespace App\Filament\Resources\PricePositions\Pages;

use App\Filament\Resources\PricePositions\PricePositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPricePositions extends ListRecords
{
    protected static string $resource = PricePositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
