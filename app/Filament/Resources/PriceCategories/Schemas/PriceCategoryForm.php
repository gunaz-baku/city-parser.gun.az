<?php

namespace App\Filament\Resources\PriceCategories\Schemas;

use App\Filament\Support\TranslatedNameFields;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PriceCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatedNameFields::nameSection('Category names'),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(160)
                    ->unique(ignoreRecord: true),
                TextInput::make('icon')
                    ->label('Icon')
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Toggle::make('show_in_page')
                    ->label('Show in page')
                    ->default(true),
            ]);
    }
}
