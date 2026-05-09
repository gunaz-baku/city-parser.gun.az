<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

final class TranslatedNameFields
{
    /**
     * Section with English (required for forms that need a list label), Azerbaijani, Russian — optional except English where required.
     */
    public static function nameSection(string $heading = 'Names', bool $requireEnglish = true): Section
    {
        return Section::make($heading)
            ->description('Lists and dropdowns use English only. Azerbaijani and Russian are optional.')
            ->schema([
                TextInput::make('name_en')
                    ->label('Name (English)')
                    ->maxLength(255)
                    ->required($requireEnglish),
                TextInput::make('name_az')
                    ->label('Name (Azerbaijani)')
                    ->maxLength(255),
                TextInput::make('name_ru')
                    ->label('Name (Russian)')
                    ->maxLength(255),
            ])
            ->columns(3);
    }
}
