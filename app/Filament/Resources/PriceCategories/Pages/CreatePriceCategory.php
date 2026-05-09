<?php

namespace App\Filament\Resources\PriceCategories\Pages;

use App\Filament\Resources\PriceCategories\PriceCategoryResource;
use App\Filament\Support\TranslatedJsonMerge;
use Filament\Resources\Pages\CreateRecord;

class CreatePriceCategory extends CreateRecord
{
    protected static string $resource = PriceCategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TranslatedJsonMerge::mergeFlatJsonLocales($data, [], 'name');
    }
}
