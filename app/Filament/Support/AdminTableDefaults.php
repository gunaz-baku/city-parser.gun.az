<?php

namespace App\Filament\Support;

use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AdminTableDefaults
{
    /**
     * CostOfLivingResource tərzi: View / Edit / Delete qrupu, boş vəziyyət, zolaqlı cədvəl.
     *
     * @param  string|BackedEnum|null  $emptyIcon  Heroicon və ya heroicon string
     */
    public static function applyStandard(
        Table $table,
        string $emptyHeading,
        string $emptyDescription,
        string|BackedEnum|null $emptyIcon = null,
    ): Table {
        return $table
            ->striped()
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading($emptyHeading)
            ->emptyStateDescription($emptyDescription)
            ->emptyStateIcon($emptyIcon ?? Heroicon::OutlinedRectangleStack)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    public static function createdAtFilter(): Filter
    {
        return Filter::make('created_at')
            ->label('Created date')
            ->schema([
                DatePicker::make('created_from')
                    ->label('Created from'),
                DatePicker::make('created_until')
                    ->label('Created until'),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        $data['created_from'] ?? null,
                        fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date),
                    )
                    ->when(
                        $data['created_until'] ?? null,
                        fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date),
                    );
            });
    }

    public static function occurredAtFilter(string $column = 'occurred_at'): Filter
    {
        return Filter::make($column)
            ->label('Occurred date')
            ->schema([
                DatePicker::make('occurred_from')
                    ->label('Occurred from'),
                DatePicker::make('occurred_until')
                    ->label('Occurred until'),
            ])
            ->query(function (Builder $query, array $data) use ($column): Builder {
                return $query
                    ->when(
                        $data['occurred_from'] ?? null,
                        fn (Builder $q, $date): Builder => $q->whereDate($column, '>=', $date),
                    )
                    ->when(
                        $data['occurred_until'] ?? null,
                        fn (Builder $q, $date): Builder => $q->whereDate($column, '<=', $date),
                    );
            });
    }

    /**
     * For queries that GROUP rows (synthetic `occurred_at` aliases), filter by any underlying row date.
     */
    public static function occurredAtFilterForGroupedParserRunErrors(): Filter
    {
        return Filter::make('occurred_at_any_row')
            ->label('Occurred date')
            ->schema([
                DatePicker::make('occurred_from')
                    ->label('Occurred from'),
                DatePicker::make('occurred_until')
                    ->label('Occurred until'),
            ])
            ->query(function (Builder $query, array $data): Builder {
                $from = $data['occurred_from'] ?? null;
                $until = $data['occurred_until'] ?? null;

                if ($from === null && $until === null) {
                    return $query;
                }

                return $query->whereExists(function (QueryBuilder $sub) use ($from, $until): void {
                    $sub->from('parser_run_errors as pre_occ')
                        ->whereColumn('pre_occ.parser_run_id', 'parser_run_errors.parser_run_id')
                        ->whereColumn('pre_occ.position_id', 'parser_run_errors.position_id')
                        ->whereColumn('pre_occ.source_id', 'parser_run_errors.source_id');

                    if ($from !== null) {
                        $sub->whereDate('pre_occ.occurred_at', '>=', $from);
                    }

                    if ($until !== null) {
                        $sub->whereDate('pre_occ.occurred_at', '<=', $until);
                    }
                });
            });
    }
}
