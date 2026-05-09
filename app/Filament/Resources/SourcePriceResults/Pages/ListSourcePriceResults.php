<?php

namespace App\Filament\Resources\SourcePriceResults\Pages;

use App\Filament\Resources\SourcePriceResults\SourcePriceResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSourcePriceResults extends ListRecords
{
    protected static string $resource = SourcePriceResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
