<?php

namespace App\Filament\Resources\PriceCategories\Pages;

use App\Filament\Resources\PriceCategories\PriceCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPriceCategories extends ListRecords
{
    protected static string $resource = PriceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
