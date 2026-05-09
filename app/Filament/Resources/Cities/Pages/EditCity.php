<?php

namespace App\Filament\Resources\Cities\Pages;

use App\Filament\Resources\Cities\CityResource;
use App\Filament\Support\TranslatedJsonMerge;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCity extends EditRecord
{
    protected static string $resource = CityResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['name']);

        $name = $this->record->name;
        if (is_array($name)) {
            $data['name_en'] = (string) ($name['en'] ?? '');
            $data['name_az'] = (string) ($name['az'] ?? '');
            $data['name_ru'] = (string) ($name['ru'] ?? '');
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
