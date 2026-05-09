<?php

namespace App\Filament\Resources\ApiSyncLogs\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApiSyncLogsTable
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
                    TextColumn::make('entity_type')
                        ->label('Tip')
                        ->badge(),
                    TextColumn::make('entity_id')
                        ->label('Obyekt ID')
                        ->sortable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge(),
                    TextColumn::make('response_status')
                        ->label('HTTP'),
                    TextColumn::make('attempt')
                        ->label('Cəhd'),
                    TextColumn::make('synced_at')
                        ->label('Sinxron')
                        ->dateTime('d.m.Y H:i'),
                    TextColumn::make('created_at')
                        ->label('Yaradılıb')
                        ->dateTime('d.m.Y H:i')
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('updated_at')
                        ->label('Yenilənib')
                        ->dateTime('d.m.Y H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                ->filters([
                    SelectFilter::make('status')
                        ->label('Status')
                        ->options([
                            'pending' => 'Gözləyir',
                            'success' => 'Uğur',
                            'failed' => 'Xəta',
                        ]),
                    AdminTableDefaults::createdAtFilter(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'Heç bir API sinxron jurnalı yoxdur',
            'Gun.az ilə sinxron cəhdləri burada izləyə bilərsiniz.',
            Heroicon::OutlinedCloudArrowUp,
        );
    }
}
