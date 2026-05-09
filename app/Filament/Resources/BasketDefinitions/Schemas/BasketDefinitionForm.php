<?php

namespace App\Filament\Resources\BasketDefinitions\Schemas;

use App\Filament\Support\TranslatedNameFields;
use App\Models\BasketDefinition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BasketDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatedNameFields::nameSection('Basket names'),
                Select::make('type')
                    ->label('Type')
                    ->options([
                        BasketDefinition::TYPE_BASKET => 'Basket',
                        BasketDefinition::TYPE_BADGE => 'Badge',
                    ])
                    ->default(BasketDefinition::TYPE_BASKET)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
