<?php

namespace App\Filament\Resources\SourcePriceResults\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SourcePriceResultsTable
{
    public static function configure(Table $table): Table
    {
        return AdminTableDefaults::applyStandard(
            $table
                ->modifyQueryUsing(fn ($query) => $query->with('position'))
                ->defaultSort('id', 'desc')
                ->columns([
                    TextColumn::make('id')
                        ->label('ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('parserRun.id')
                        ->label('İş')
                        ->sortable(),
                    TextColumn::make('position.slug')
                        ->label('Mövqe')
                        ->searchable(),
                    TextColumn::make('result_date')
                        ->label('Tarix')
                        ->date('d.m.Y'),
                    TextColumn::make('normalized_price')
                        ->label('Qiymət')
                        ->numeric(decimalPlaces: 4),
                    TextColumn::make('currency')
                        ->label('Val'),
                    IconColumn::make('is_valid')
                        ->label('OK')
                        ->boolean(),
                    TextColumn::make('created_at')
                        ->label('Yaradılıb')
                        ->dateTime('d.m.Y H:i')
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
            'Mənbə qiymət nəticəsi yoxdur',
            'Parser mənbədən qiymət götürdükdə sətirlər burada görünəcək.',
            Heroicon::OutlinedBanknotes,
        );
    }
}
