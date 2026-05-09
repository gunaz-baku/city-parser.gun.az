<?php

namespace App\Filament\Resources\BasketDefinitions\Pages;

use App\Filament\Resources\BasketDefinitions\BasketDefinitionResource;
use App\Filament\Support\TranslatedJsonMerge;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBasketDefinition extends EditRecord
{
    protected static string $resource = BasketDefinitionResource::class;

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existing = is_array($this->record->name) ? $this->record->name : [];

        return TranslatedJsonMerge::mergeFlatJsonLocales($data, $existing, 'name');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
