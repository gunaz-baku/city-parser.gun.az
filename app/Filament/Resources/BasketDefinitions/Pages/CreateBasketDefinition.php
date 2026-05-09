<?php

namespace App\Filament\Resources\BasketDefinitions\Pages;

use App\Filament\Resources\BasketDefinitions\BasketDefinitionResource;
use App\Filament\Support\TranslatedJsonMerge;
use Filament\Resources\Pages\CreateRecord;

class CreateBasketDefinition extends CreateRecord
{
    protected static string $resource = BasketDefinitionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TranslatedJsonMerge::mergeFlatJsonLocales($data, [], 'name');
    }
}
