<?php

namespace App\Filament\Resources\BasketPriceCalculationErrors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BasketPriceCalculationErrorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('basket_id')
                    ->numeric(),
                TextInput::make('basket_item_id')
                    ->numeric(),
                TextInput::make('position_id')
                    ->numeric(),
                TextInput::make('error_type')
                    ->required(),
                Textarea::make('message')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('context'),
                TextInput::make('calculation_run_id')
                    ->numeric(),
            ]);
    }
}
