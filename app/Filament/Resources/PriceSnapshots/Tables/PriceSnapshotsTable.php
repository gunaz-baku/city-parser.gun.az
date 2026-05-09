<?php

namespace App\Filament\Resources\PriceSnapshots\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PriceSnapshotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('position.name')
                    ->label('Ad')
                    ->formatStateUsing(function ($state): string {
                        if (is_array($state)) {
                            $az = $state['az'] ?? null;
                            if (is_string($az) && $az !== '') {
                                return $az;
                            }

                            return Str::limit(json_encode($state, JSON_UNESCAPED_UNICODE), 120);
                        }

                        return (string) $state;
                    })
                    ->description(fn ($record) => $record->position?->slug)
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('position', function ($q) use ($search): void {
                            $q->where('slug', 'like', "%{$search}%")
                                ->orWhere('name->az', 'like', "%{$search}%")
                                ->orWhere('name->ru', 'like', "%{$search}%")
                                ->orWhere('name->en', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('snapshot_date')->label('Tarix')->date()->sortable(),
                TextColumn::make('price_avg')->label('Orta')->numeric(decimalPlaces: 4),
                TextColumn::make('parser_type')->label('Parser')->badge(),
                TextColumn::make('sync_status')->label('Sinxron')->badge(),
                TextColumn::make('synced_at')->label('Sinxron vaxt')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('sync_status')
                    ->label('Sinxron')
                    ->options([
                        'pending' => 'Gözləyir',
                        'synced' => 'Sinxron',
                        'failed' => 'Xəta',
                    ]),
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
