<?php

namespace App\Filament\Resources\Units\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(40)
                    ->unique(ignoreRecord: true)
                    ->helperText('Stable identifier (e.g. kg, ml, pcs).'),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(120),
                TextInput::make('short_name')
                    ->label('Short name')
                    ->required()
                    ->maxLength(40)
                    ->helperText('Shown next to quantity (g, ml, …).'),
                Select::make('unit_type')
                    ->label('Type')
                    ->options([
                        'weight' => 'Weight',
                        'volume' => 'Volume',
                        'count' => 'Count',
                    ])
                    ->required(),
                TextInput::make('base_unit')
                    ->label('Base unit')
                    ->maxLength(40)
                    ->nullable(),
                TextInput::make('multiplier')
                    ->label('Multiplier')
                    ->numeric()
                    ->nullable()
                    ->helperText('How many base units (e.g. 1000 for kg → g).'),
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
