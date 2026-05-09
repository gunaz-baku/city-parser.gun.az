<?php

namespace App\Filament\Resources\BasketPriceCalculationErrors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BasketPriceCalculationErrorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('basket_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('basket_item_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('position_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('error_type')
                    ->searchable(),
                TextColumn::make('calculation_run_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
