<?php

namespace App\Filament\Resources\PriceUnits\Tables;

use App\Filament\Support\AdminTableDefaults;
use App\Models\PriceUnit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PriceUnitsTable
{
    public static function configure(Table $table): Table
    {
        return AdminTableDefaults::applyStandard(
            $table
                ->defaultSort('sort_order')
                ->columns([
                    TextColumn::make('id')
                        ->label('ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('code')
                        ->label('Code')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('label')
                        ->label('Label')
                        ->wrap()
                        ->searchable(),
                    TextColumn::make('display')
                        ->label('Display')
                        ->state(fn (PriceUnit $record): string => $record->displayLabel())
                        ->wrap()
                        ->toggleable(isToggledHiddenByDefault: true),
                    IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean(),
                    TextColumn::make('sort_order')
                        ->label('Sort')
                        ->sortable(),
                ])
                ->filters([
                    TernaryFilter::make('is_active')->label('Active'),
                    AdminTableDefaults::createdAtFilter(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'No units yet',
            'Define measurement units once; products reference them by selection.',
            Heroicon::OutlinedScale,
        );
    }
}
