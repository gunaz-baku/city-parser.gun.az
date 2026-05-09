<?php

namespace App\Filament\Resources\ParserRuns\Tables;

use App\Filament\Support\AdminTableDefaults;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ParserRunsTable
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
                    TextColumn::make('parser_type')
                        ->label('Parser type')
                        ->badge()
                        ->searchable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            'running' => 'warning',
                            'success' => 'success',
                            'partial' => 'warning',
                            'failed' => 'danger',
                            'cancelled' => 'gray',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : Str::headline($state)),
                    TextColumn::make('started_at')
                        ->label('Started')
                        ->dateTime('Y-m-d H:i')
                        ->sortable(),
                    TextColumn::make('finished_at')
                        ->label('Finished')
                        ->dateTime('Y-m-d H:i'),
                    TextColumn::make('duration_seconds')
                        ->label('Duration')
                        ->formatStateUsing(fn ($state): string => $state === null ? '—' : (string) $state.' s'),
                    TextColumn::make('total_positions')
                        ->label('Total products'),
                    TextColumn::make('success_positions')
                        ->label('Success'),
                    TextColumn::make('failed_positions')
                        ->label('Failed'),
                    TextColumn::make('skipped_positions')
                        ->label('Skipped'),
                ])
                ->filters([
                    SelectFilter::make('status')
                        ->label('Status')
                        ->options([
                            'running' => 'Running',
                            'success' => 'Success',
                            'partial' => 'Partial',
                            'failed' => 'Failed',
                            'cancelled' => 'Cancelled',
                        ]),
                    AdminTableDefaults::createdAtFilter(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'No parser runs yet',
            'Queued runs will appear here with status and counts.',
            Heroicon::OutlinedPlay,
        );
    }
}
