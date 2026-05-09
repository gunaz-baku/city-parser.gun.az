<?php

namespace App\Filament\Resources\Units\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UnitsTable
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
                    TextColumn::make('name')
                        ->label('Name')
                        ->wrap()
                        ->searchable(),
                    TextColumn::make('code')
                        ->label('Code')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('short_name')
                        ->label('Short name')
                        ->searchable(),
                    TextColumn::make('unit_type')
                        ->label('Type')
                        ->badge(),
                    TextColumn::make('base_unit')
                        ->label('Base unit')
                        ->placeholder('—'),
                    TextColumn::make('multiplier')
                        ->label('Multiplier')
                        ->placeholder('—'),
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
