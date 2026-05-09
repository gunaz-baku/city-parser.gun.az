<?php

namespace App\Filament\Resources\Cities\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CitiesTable
{
    public static function configure(Table $table): Table
    {
        return AdminTableDefaults::applyStandard(
            $table
                ->defaultSort('id')
                ->columns([
                    TextColumn::make('id')
                        ->label('ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('name_en')
                        ->label('Name')
                        ->wrap()
                        ->placeholder('—')
                        ->searchable(query: function ($query, string $search): void {
                            $query->where(function ($q) use ($search): void {
                                foreach (['en', 'az', 'ru'] as $locale) {
                                    $q->orWhere("name->{$locale}", 'like', "%{$search}%");
                                }
                            });
                        }),
                    IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean(),
                    TextColumn::make('created_at')
                        ->label('Created')
                        ->dateTime('Y-m-d H:i')
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
            'No cities yet',
            'Add locations that power city-aware parsing and admin dictionaries.',
            Heroicon::OutlinedBuildingOffice2,
        );
    }
}
