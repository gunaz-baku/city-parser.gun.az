<?php

namespace App\Filament\Resources\PricePositions\Pages;

use App\Filament\Resources\PricePositions\PricePositionResource;
use App\Filament\Support\TranslatedJsonMerge;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPricePosition extends EditRecord
{
    protected static string $resource = PricePositionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['name']);

        $n = $this->record->name;
        if (is_array($n)) {
            $data['name_en'] = (string) ($n['en'] ?? '');
            $data['name_az'] = (string) ($n['az'] ?? '');
            $data['name_ru'] = (string) ($n['ru'] ?? '');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existingName = is_array($this->record->name) ? $this->record->name : [];

        return TranslatedJsonMerge::mergeFlatJsonLocales($data, $existingName, 'name');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
