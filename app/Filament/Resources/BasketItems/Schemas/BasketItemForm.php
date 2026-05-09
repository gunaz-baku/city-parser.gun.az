<?php

namespace App\Filament\Resources\BasketItems\Schemas;

use App\Models\Unit;
use App\Models\BasketDefinition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class BasketItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('basket_id')
                    ->label('Basket')
                    ->relationship('basket', 'id')
                    ->getOptionLabelFromRecordUsing(fn (BasketDefinition $record): string => $record->name_en ?? ('#'.$record->id))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('position_id')
                    ->label('Product')
                    ->relationship('position', 'slug')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('qty')
                    ->label('Quantity')
                    ->numeric()
                    ->required(),
                Select::make('unit_id')
                    ->label('Unit')
                    ->relationship(
                        name: 'unit',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Unit $record): string => $record->displayLabel())
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }
}
