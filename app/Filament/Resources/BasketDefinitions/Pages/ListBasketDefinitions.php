<?php

namespace App\Filament\Resources\BasketDefinitions\Pages;

use App\Filament\Resources\BasketDefinitions\BasketDefinitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBasketDefinitions extends ListRecords
{
    protected static string $resource = BasketDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
