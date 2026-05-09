<?php

namespace App\Filament\Resources\PricePositions\Schemas;

use App\Filament\Support\TranslatedNameFields;
use App\Models\PriceCategory;
use App\Models\Unit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PricePositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'slug')
                    ->getOptionLabelFromRecordUsing(fn (PriceCategory $record): string => ($record->name_en ?? '—').' — '.$record->slug)
                    ->searchable()
                    ->preload()
                    ->required(),
                TranslatedNameFields::nameSection('Product names'),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(180),
                Select::make('unit_id')
                    ->label('Unit')
                    ->relationship(
                        name: 'measurementUnit',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Unit $record): string => $record->displayLabel())
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('unit_size')
                    ->label('Unit size')
                    ->numeric()
                    ->nullable()
                    ->helperText('Məs: 500 (g üçün), 1 (kq üçün) — vahid növü yuxarıdakı Unit ilə birlikdə.'),
                Select::make('parser_type')
                    ->label('Parser type')
                    ->options([
                        'manual' => 'Manual',
                        'wolt' => 'Wolt',
                        'bina' => 'Bina',
                        'gun_az' => 'Gun.az',
                    ])
                    ->default('manual')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
