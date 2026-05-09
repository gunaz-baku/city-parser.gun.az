<?php

namespace App\Support\Admin;

use App\Models\ParserRunError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class ParserRunErrorsGroupedTableQuery
{
    /**
     * Same aggregation as {@see \App\Filament\Resources\ParserRunErrors\Tables\ParserRunErrorsTable}.
     */
    public static function applyAggregation(Builder $query): Builder
    {
        return $query
            ->selectRaw(implode(', ', [
                'MIN(parser_run_errors.id) as id',
                'parser_run_errors.parser_run_id',
                'parser_run_errors.position_id',
                'parser_run_errors.source_id',
                'MAX(parser_run_errors.occurred_at) as occurred_at',
                'MAX(parser_run_errors.created_at) as created_at',
                'COUNT(*) as errors_count',
                "GROUP_CONCAT(DISTINCT parser_run_errors.error_stage ORDER BY parser_run_errors.error_stage SEPARATOR ',') as stages_csv",
                "GROUP_CONCAT(DISTINCT parser_run_errors.error_code ORDER BY parser_run_errors.error_code SEPARATOR ',') as codes_csv",
                "GROUP_CONCAT(
                    CONCAT(
                        '[', DATE_FORMAT(parser_run_errors.occurred_at, '%Y-%m-%d %H:%i'), '] ',
                        COALESCE(parser_run_errors.error_stage, ''),
                        ': ',
                        LEFT(REPLACE(REPLACE(parser_run_errors.error_message, '\r', ' '), '\n', ' '), 240)
                    )
                    ORDER BY ANY_VALUE(parser_run_errors.occurred_at) ASC, ANY_VALUE(parser_run_errors.id) ASC
                    SEPARATOR '\n'
                ) as lines_blob",
                "GROUP_CONCAT(
                    NULLIF(
                        TRIM(
                            COALESCE(
                                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(parser_run_errors.error_context, '$.url')), ''),
                                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(parser_run_errors.error_context, '$.primary_url')), ''),
                                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(parser_run_errors.error_context, '$.successful_url')), '')
                            )
                        ),
                        ''
                    )
                    ORDER BY ANY_VALUE(parser_run_errors.id) ASC
                    SEPARATOR '\n'
                ) as urls_blob",
            ]))
            ->groupBy([
                'parser_run_errors.parser_run_id',
                'parser_run_errors.position_id',
                'parser_run_errors.source_id',
            ])
            ->with(['position', 'source', 'parserRun']);
    }

    public static function newGroupedBuilder(): Builder
    {
        return self::applyAggregation(ParserRunError::query());
    }

    /**
     * Filter by any underlying row’s occurred_at (matches Filament grouped filter).
     */
    public static function applyOccurredDateFilter(Builder $query, mixed $from, mixed $until): Builder
    {
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
    }
}
