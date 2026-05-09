<?php

namespace App\Filament\Resources\ParserRunErrors\Tables;

use App\Filament\Support\AdminTableDefaults;
use App\Models\ParserRunError;
use App\Support\Admin\ParserRunErrorsGroupedTableQuery;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ParserRunErrorsTable
{
    public static function configure(Table $table): Table
    {
        $table = AdminTableDefaults::applyStandard(
            $table
                ->modifyQueryUsing(function ($query) {
                    // One row per product/source within a parser run (aggregate underlying error rows).
                    return ParserRunErrorsGroupedTableQuery::applyAggregation($query);
                })
                ->defaultSort('occurred_at', 'desc')
                // Grouped query: appending ORDER BY primary key breaks ONLY_FULL_GROUP_BY.
                ->defaultKeySort(false)
                ->columns([
                    TextColumn::make('parser_run_id')
                        ->label('Run')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('position.name_en')
                        ->label('Product')
                        ->placeholder('—')
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            $term = '%'.trim($search).'%';

                            return $query->whereHas('position', function (Builder $positionQuery) use ($term): void {
                                $positionQuery
                                    ->where('slug', 'like', $term)
                                    ->orWhere('name->en', 'like', $term)
                                    ->orWhere('name->az', 'like', $term)
                                    ->orWhere('name->ru', 'like', $term);
                            });
                        }),
                    TextColumn::make('source_label')
                        ->label('Source')
                        ->state(function (ParserRunError $record): string {
                            $s = $record->source;
                            if ($s === null) {
                                return '—';
                            }
                            $n = is_array($s->links_json) ? count($s->links_json) : 0;

                            return '#'.$s->id.' '.$s->source_type.' ('.$n.' link)';
                        }),
                    TextColumn::make('errors_count')
                        ->label('Errors')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('stages_csv')
                        ->label('Stages')
                        ->limit(40)
                        ->wrap()
                        ->placeholder('—'),
                    TextColumn::make('summary')
                        ->label('Summary')
                        ->state(function (ParserRunError $record): string {
                            $urls = self::splitLines((string) ($record->getAttribute('urls_blob') ?? ''));
                            $urls = array_values(array_filter($urls, static fn (string $u): bool => $u !== ''));
                            if ($urls === []) {
                                return 'Problem link tapılmadı (error_context boş və ya URL extract olunmur).';
                            }

                            $title = self::productTitleFromRecord($record);

                            $parts = [];
                            foreach ($urls as $i => $u) {
                                $idx = $i + 1;
                                $parts[] = "{$idx}-ci linkdə problem var — {$u}";
                            }

                            return $title.': '.implode(' | ', $parts);
                        })
                        ->wrap(),
                    TextColumn::make('links')
                        ->label('Links')
                        ->state(function (ParserRunError $record): string {
                            $urls = self::splitLines((string) ($record->getAttribute('urls_blob') ?? ''));

                            return $urls !== [] ? implode("\n", $urls) : '—';
                        })
                        ->wrap()
                        ->placeholder('—'),
                    TextColumn::make('occurred_at')
                        ->label('Last occurred')
                        ->dateTime('Y-m-d H:i')
                        ->sortable(),
                ])
                ->filters([
                    SelectFilter::make('parser_run_id')
                        ->label('Run')
                        ->relationship('parserRun', 'id')
                        ->searchable()
                        ->preload(),
                    AdminTableDefaults::occurredAtFilterForGroupedParserRunErrors(),
                ])
                ->toolbarActions([
                    // Bulk delete is unsafe for aggregated rows (synthetic ids) — use per-row delete.
                ]),
            'No parser errors',
            'Technical details from failed parser stages appear here.',
            Heroicon::OutlinedExclamationTriangle,
        );

        return $table
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
                Action::make('viewDetails')
                    ->label('Details')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Parser error details (grouped)')
                    ->modalSubmitAction(false)
                    ->modalContent(function (ParserRunError $record): HtmlString {
                        $lines = self::splitLines((string) ($record->getAttribute('lines_blob') ?? ''));
                        $urls = self::splitLines((string) ($record->getAttribute('urls_blob') ?? ''));

                        $body = '';
                        $body .= '<div class="fi-text-sm fi-font-semibold">Links</div>';
                        if ($urls === []) {
                            $body .= '<div class="fi-text-sm fi-text-gray-600">—</div>';
                        } else {
                            $body .= '<ul class="fi-mt-2 fi-list-inside fi-list-disc fi-space-y-1">';
                            foreach ($urls as $u) {
                                $body .= '<li class="fi-break-all"><a class="fi-link fi-text-primary-600" href="'.e($u).'" target="_blank" rel="noreferrer">'.e($u).'</a></li>';
                            }
                            $body .= '</ul>';
                        }

                        $body .= '<div class="fi-mt-4 fi-text-sm fi-font-semibold">Events</div>';
                        if ($lines === []) {
                            $body .= '<div class="fi-text-sm fi-text-gray-600">—</div>';
                        } else {
                            $body .= '<pre class="fi-mt-2 fi-text-xs max-h-[32rem] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-zinc-950 p-4 text-zinc-100 dark:bg-zinc-900" style="overflow-wrap:anywhere;">'
                                .e(implode("\n", $lines))
                                .'</pre>';
                        }

                        return new HtmlString($body);
                    }),
                Action::make('deleteGroup')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ParserRunError $record): void {
                        $runId = (int) ($record->parser_run_id ?? 0);
                        $posId = $record->position_id !== null ? (int) $record->position_id : null;
                        $srcId = $record->source_id !== null ? (int) $record->source_id : null;

                        if ($runId < 1) {
                            return;
                        }

                        $q = DB::table('parser_run_errors')->where('parser_run_id', $runId);
                        if ($posId !== null && $posId > 0) {
                            $q->where('position_id', $posId);
                        } else {
                            $q->whereNull('position_id');
                        }
                        if ($srcId !== null && $srcId > 0) {
                            $q->where('source_id', $srcId);
                        } else {
                            $q->whereNull('source_id');
                        }

                        $q->delete();
                    }),
            ]);
    }

    /**
     * @return list<string>
     */
    private static function splitLines(string $blob): array
    {
        $blob = trim($blob);
        if ($blob === '') {
            return [];
        }

        $parts = preg_split("/\r\n|\n|\r/", $blob) ?: [];

        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }

    private static function productTitleFromRecord(ParserRunError $record): string
    {
        $pos = $record->relationLoaded('position') ? $record->position : null;
        if ($pos !== null && is_string($pos->name_en ?? null) && trim((string) $pos->name_en) !== '') {
            return trim((string) $pos->name_en);
        }

        return 'Məhsul #'.(string) ($record->position_id ?? '?');
    }
}
