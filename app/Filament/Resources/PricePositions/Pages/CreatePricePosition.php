<?php

namespace App\Filament\Resources\PricePositions\Pages;

use App\Filament\Resources\PricePositions\PricePositionResource;
use App\Filament\Support\TranslatedJsonMerge;
use Filament\Resources\Pages\CreateRecord;

class CreatePricePosition extends CreateRecord
{
    protected static string $resource = PricePositionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TranslatedJsonMerge::mergeFlatJsonLocales($data, [], 'name');
    }
}
