<?php

namespace App\Filament\Resources\BasketDefinitions\Tables;

use App\Filament\Resources\BasketDefinitions\BasketDefinitionResource;
use App\Filament\Resources\BasketSnapshots\BasketSnapshotResource;
use App\Filament\Support\AdminTableDefaults;
use App\Models\BasketDefinition;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BasketDefinitionsTable
{
    public static function configure(Table $table): Table
    {
        return AdminTableDefaults::applyStandard(
            $table
                ->defaultSort('id', 'desc')
                ->columns([
                    TextColumn::make('id')
                        ->label('ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('name_en')
                        ->label('Name')
                        ->wrap()
                        ->placeholder('—'),
                    TextColumn::make('type')
                        ->label('Type')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('basket_items_count')
                        ->counts('basketItems')
                        ->label('Items')
                        ->sortable(),
                    IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean(),
                    TextColumn::make('updated_at')
                        ->label('Updated')
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
            'No baskets yet',
            'Create a basket, then add products and quantities on the edit screen.',
            Heroicon::OutlinedShoppingCart,
        )
            ->recordActions([
                EditAction::make(),
                Action::make('manageItems')
                    ->label('Manage items')
                    ->icon(Heroicon::OutlinedSquaresPlus)
                    ->url(fn (BasketDefinition $record): string => BasketDefinitionResource::getUrl('edit', ['record' => $record])),
                Action::make('latestBasketPrice')
                    ->label('Latest basket price')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->url(fn (BasketDefinition $record): string => BasketSnapshotResource::getUrl('index', [
                        'tableFilters' => [
                            'basket_id' => [
                                'value' => (string) $record->getKey(),
                            ],
                        ],
                    ])),
                DeleteAction::make(),
            ]);
    }
}
