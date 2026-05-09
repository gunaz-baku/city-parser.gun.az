<?php

namespace App\Filament\Resources\PriceUnits\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PriceUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(80)
                    ->unique(ignoreRecord: true)
                    ->helperText('Stable identifier (e.g. kg, liter, bina_sale).'),
                TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Vahid növü: kq, q, l və s.'),
                TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
