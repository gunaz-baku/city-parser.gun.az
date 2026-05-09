<?php

namespace App\Filament\Resources\PriceSources\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PriceSourcesTable
{
    public static function configure(Table $table): Table
    {
        return AdminTableDefaults::applyStandard(
            $table
                ->defaultSort('id', 'desc')
                ->modifyQueryUsing(fn ($query) => $query->with('position'))
                ->columns([
                    TextColumn::make('id')
                        ->label('ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('position.slug')
                        ->label('Mövqe')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('source_type')
                        ->label('Tip')
                        ->badge(),
                    TextColumn::make('links_count')
                        ->label('Link sayı')
                        ->state(fn ($record): int => is_array($record->links_json ?? null) ? count($record->links_json) : 0),
                    TextColumn::make('links_preview')
                        ->label('Linklər (qısa)')
                        ->limit(60)
                        ->wrap()
                        ->state(function ($record): string {
                            $links = $record->links_json ?? [];
                            if (! is_array($links) || $links === []) {
                                return '—';
                            }
                            $first = trim((string) ($links[0] ?? ''));

                            return $first !== '' ? $first : '—';
                        }),
                    TextColumn::make('priority')
                        ->label('Prioritet')
                        ->sortable(),
                    IconColumn::make('is_active')
                        ->label('Aktiv')
                        ->boolean(),
                    TextColumn::make('created_at')
                        ->label('Yaradılıb')
                        ->dateTime('d.m.Y H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->label('Yenilənib')
                        ->dateTime('d.m.Y H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                ->filters([
                    TernaryFilter::make('is_active')->label('Aktiv'),
                    AdminTableDefaults::createdAtFilter(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'Heç bir qiymət mənbəyi tapılmadı',
            'Mövqe üçün mənbə əlavə edin.',
            Heroicon::OutlinedLink,
        );
    }
}
