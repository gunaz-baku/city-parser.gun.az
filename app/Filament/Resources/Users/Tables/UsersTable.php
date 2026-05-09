<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
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
                    TextColumn::make('name')
                        ->label('Ad')
                        ->searchable()
                        ->wrap(),
                    TextColumn::make('email')
                        ->label('E-poçt')
                        ->searchable(),
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
                    AdminTableDefaults::createdAtFilter(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'İstifadəçi yoxdur',
            'Panel üçün ilk istifadəçini əlavə edin.',
            Heroicon::OutlinedUsers,
        );
    }
}
