<?php

namespace App\Filament\Resources\PriceSources\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PriceSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('position_id')
                    ->label('Mövqe')
                    ->relationship('position', 'slug')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('source_type')
                    ->label('Mənbə tipi')
                    ->options([
                        'wolt' => 'Wolt',
                        'bina' => 'Bina',
                        'manual' => 'Manual',
                    ])
                    ->required()
                    ->native(false),
                Repeater::make('links_json')
                    ->label('Linklər')
                    ->helperText('Hər sətirdə bir URL. Parser `source_type`-a uyğun bütün linkləri işləyir.')
                    ->simple(
                        TextInput::make('')
                            ->url()
                            ->maxLength(2048)
                            ->placeholder('https://')
                    )
                    ->defaultItems(0)
                    ->addActionLabel('URL əlavə et')
                    ->columnSpanFull()
                    ->dehydrateStateUsing(function (mixed $state): array {
                        if (! is_array($state)) {
                            return [];
                        }

                        $out = [];
                        foreach ($state as $v) {
                            if (! is_string($v)) {
                                continue;
                            }
                            $s = trim($v);
                            if ($s !== '') {
                                $out[] = $s;
                            }
                        }

                        return array_values(array_unique($out));
                    }),
                TextInput::make('priority')
                    ->label('Prioritet')
                    ->numeric()
                    ->default(100)
                    ->minValue(0)
                    ->maxValue(65535),
                Toggle::make('is_active')
                    ->label('Aktiv')
                    ->default(true),
            ]);
    }
}
