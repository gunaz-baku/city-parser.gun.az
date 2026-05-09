<?php

namespace App\Filament\Resources\PriceCategories\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PriceCategoriesTable
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
                    TextColumn::make('sort_order')
                        ->label('Sort order')
                        ->sortable(),
                    IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean(),
                    IconColumn::make('show_in_page')
                        ->label('Show in page')
                        ->boolean(),
                    TextColumn::make('created_at')
                        ->label('Created')
                        ->dateTime('Y-m-d H:i')
                        ->sortable(),
                ])
                ->filters([
                    TernaryFilter::make('is_active')->label('Active'),
                    TernaryFilter::make('show_in_page')->label('Show in page'),
                    AdminTableDefaults::createdAtFilter(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'No categories yet',
            'Create the catalog hierarchy your products will belong to.',
            Heroicon::OutlinedFolder,
        );
    }
}
