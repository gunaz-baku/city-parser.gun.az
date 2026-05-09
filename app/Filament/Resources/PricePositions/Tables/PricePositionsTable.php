<?php

namespace App\Filament\Resources\PricePositions\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PricePositionsTable
{
    public static function configure(Table $table): Table
    {
        return AdminTableDefaults::applyStandard(
            $table
                ->modifyQueryUsing(function ($query): void {
                    $query->with(['category', 'measurementUnit']);
                })
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
                    TextColumn::make('category.name_en')
                        ->label('Category')
                        ->placeholder('—'),
                    TextColumn::make('unit_label_en')
                        ->label('Unit type')
                        ->placeholder('—'),
                    TextColumn::make('unit_size')
                        ->label('Unit size')
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('parser_type')
                        ->label('Parser type')
                        ->badge(),
                    IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean(),
                    TextColumn::make('sort_order')
                        ->label('Sort order')
                        ->sortable(),
                    TextColumn::make('updated_at')
                        ->label('Updated')
                        ->dateTime('Y-m-d H:i')
                        ->sortable(),
                ])
                ->filters([
                    TernaryFilter::make('is_active')->label('Active'),
                    SelectFilter::make('category_id')
                        ->label('Category')
                        ->relationship('category', 'slug')
                        ->searchable()
                        ->preload(),
                    SelectFilter::make('parser_type')
                        ->label('Parser type')
                        ->options([
                            'manual' => 'Manual',
                            'wolt' => 'Wolt',
                            'bina' => 'Bina',
                            'gun_az' => 'Gun.az',
                        ]),
                    AdminTableDefaults::createdAtFilter(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'No products yet',
            'Create products for each category, or import them from the parser pipeline.',
            Heroicon::OutlinedTag,
        );
    }
}
