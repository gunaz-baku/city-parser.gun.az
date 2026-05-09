<?php

namespace App\Filament\Resources\PositionFailures\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PositionFailuresTable
{
    public static function configure(Table $table): Table
    {
        return AdminTableDefaults::applyStandard(
            $table
                ->modifyQueryUsing(fn ($query) => $query->with(['position.category', 'parserRun']))
                ->defaultSort('id', 'desc')
                ->columns([
                    TextColumn::make('id')
                        ->label('ID')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('parserRun.id')
                        ->label('Run ID')
                        ->sortable(),
                    TextColumn::make('position.name_en')
                        ->label('Product')
                        ->placeholder('—'),
                    TextColumn::make('failure_date')
                        ->label('Failure date')
                        ->date('Y-m-d'),
                    TextColumn::make('reason')
                        ->label('Reason')
                        ->limit(80)
                        ->wrap(),
                    TextColumn::make('created_at')
                        ->label('Logged at')
                        ->dateTime('Y-m-d H:i')
                        ->sortable(),
                ])
                ->filters([
                    SelectFilter::make('parser_run_id')
                        ->label('Run')
                        ->relationship('parserRun', 'id')
                        ->searchable()
                        ->preload(),
                    AdminTableDefaults::createdAtFilter(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'No product failures',
            'When the parser cannot price a product, a row is recorded here.',
            Heroicon::OutlinedXCircle,
        )
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
