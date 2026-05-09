<?php

namespace App\Filament\Resources\BasketSnapshots\Tables;

use App\Filament\Support\AdminTableDefaults;
use App\Models\BasketDefinition;
use App\Models\BasketSnapshot;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class BasketSnapshotsTable
{
    public static function configure(Table $table): Table
    {
        $columns = [
            TextColumn::make('basket.name_en')
                ->label('Basket')
                ->placeholder('—'),
            TextColumn::make('snapshot_date')
                ->label('Snapshot date')
                ->date('Y-m-d')
                ->sortable(),
            TextColumn::make('total_price')
                ->label('Total price')
                ->numeric(decimalPlaces: 2),
            TextColumn::make('dolma_index_total')
                ->label('Dolma index (snapshot)')
                ->numeric(decimalPlaces: 2)
                ->placeholder('—')
                ->visible(Schema::hasColumn('basket_snapshots', 'dolma_index_total')),
            TextColumn::make('weekly_change')
                ->label('Weekly change')
                ->state(function (BasketSnapshot $record): string {
                    $prior = $record->comparisonSnapshotDaysAgo(7);

                    return $record->formatPercentChange($record->percentChangeVersus($prior));
                }),
            TextColumn::make('monthly_change')
                ->label('Monthly change')
                ->state(function (BasketSnapshot $record): string {
                    $prior = $record->comparisonSnapshotDaysAgo(30);

                    return $record->formatPercentChange($record->percentChangeVersus($prior));
                }),
            TextColumn::make('currency')
                ->label('Currency')
                ->badge(),
            TextColumn::make('created_at')
                ->label('Created at')
                ->dateTime('Y-m-d H:i')
                ->sortable(),
        ];

        $filters = [
            SelectFilter::make('basket_id')
                ->label('Basket')
                ->relationship('basket', 'id')
                ->getOptionLabelFromRecordUsing(fn (BasketDefinition $record): string => $record->name_en ?? ('#'.$record->id))
                ->searchable()
                ->preload(),
            Filter::make('snapshot_date')
                ->schema([
                    DatePicker::make('from')->label('Date from'),
                    DatePicker::make('until')->label('Date until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $q, $date): Builder => $q->whereDate('snapshot_date', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $q, $date): Builder => $q->whereDate('snapshot_date', '<=', $date),
                        );
                }),
            SelectFilter::make('sync_status')
                ->label('Sync status')
                ->options([
                    'pending' => 'Pending',
                    'synced' => 'Synced',
                    'failed' => 'Failed',
                ]),
            AdminTableDefaults::createdAtFilter(),
        ];

        return AdminTableDefaults::applyStandard(
            $table
                ->modifyQueryUsing(fn ($query) => $query->with(['basket']))
                ->defaultSort('snapshot_date', 'desc')
                ->columns($columns)
                ->filters($filters)
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            'No basket prices yet',
            'Totals per basket are stored after each calculation run.',
            Heroicon::OutlinedChartBar,
        );
    }
}
