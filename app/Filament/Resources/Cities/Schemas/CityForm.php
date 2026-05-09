<?php

namespace App\Filament\Resources\Cities\Schemas;

use App\Filament\Support\TranslatedNameFields;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatedNameFields::nameSection('City names'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
