<?php

namespace App\Filament\Resources\BasketItems\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BasketItemsTable
{
    public static function configure(Table $table): Table
    {
        return AdminTableDefaults::applyStandard(
            $table
                ->modifyQueryUsing(fn ($query) => $query->with(['unit', 'position']))
                ->defaultSort('id', 'desc')
                ->columns([
                    TextColumn::make('id')
                        ->label('ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('basket.name_en')
                        ->label('Basket')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('position.slug')
                        ->label('Product')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('qty')
                        ->label('Quantity')
                        ->numeric(decimalPlaces: 4),
                    TextColumn::make('unit_display')
                        ->label('Unit')
                        ->state(fn ($record): string => trim((string) ($record->unit?->short_name ?? '')) ?: (trim((string) ($record->qty_unit ?? '')) ?: '—')),
                    TextColumn::make('created_at')
                        ->label('Created')
                        ->dateTime('Y-m-d H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->label('Updated')
                        ->dateTime('Y-m-d H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                ->filters([
                    AdminTableDefaults::createdAtFilter(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'No basket lines',
            'Add products to a basket from the Baskets screen.',
            Heroicon::OutlinedSquaresPlus,
        );
    }
}
